<?php

namespace Topoff\MailManager\Services\SesSns;

use Illuminate\Support\Arr;
use RuntimeException;
use Topoff\MailManager\Contracts\SesSnsProvisioningApi;

class SesSendingSetupService
{
    public function __construct(protected SesSnsProvisioningApi $api) {}

    /**
     * @return array{ok: bool, steps: array<int, array{label: string, ok: bool, details: string}>, dns_records: array<int, array{name: string, type: string, values: array<int, string>}>}
     */
    public function setup(): array
    {
        $this->assertFeatureEnabled();

        $steps = [];
        $identity = $this->identity();
        $identityData = $this->api->getEmailIdentity($identity);

        if ($identityData === null) {
            $identityData = $this->api->createEmailIdentity($identity);
            $steps[] = ['label' => 'SES identity', 'ok' => true, 'details' => 'Created: '.$identity];
            $identityData = $this->api->getEmailIdentity($identity) ?? $identityData;
        } else {
            $steps[] = ['label' => 'SES identity', 'ok' => true, 'details' => 'Already exists: '.$identity];
        }

        $dnsRecords = $this->buildDnsRecords($identity, $identityData);

        $mailFromDomain = $this->mailFromDomain();
        if ($mailFromDomain !== null && $mailFromDomain !== '') {
            $this->api->putEmailIdentityMailFromAttributes(
                $identity,
                $mailFromDomain,
                $this->mailFromBehaviorOnMxFailure(),
            );
            $steps[] = ['label' => 'SES custom MAIL FROM', 'ok' => true, 'details' => 'Configured: '.$mailFromDomain];

            foreach ($this->buildMailFromDnsRecords($mailFromDomain) as $record) {
                $dnsRecords[] = $record;
            }
        } else {
            $steps[] = ['label' => 'SES custom MAIL FROM', 'ok' => true, 'details' => 'Skipped (not configured).'];
        }

        $this->upsertDnsIfConfigured($identity, $dnsRecords, $steps);

        $configurationSet = $this->configurationSetName();
        if ($configurationSet !== null && $configurationSet !== '') {
            $this->api->putEmailIdentityConfigurationSetAttributes($identity, $configurationSet);
            $steps[] = ['label' => 'SES default configuration set', 'ok' => true, 'details' => 'Assigned to identity: '.$configurationSet];
        } else {
            $steps[] = ['label' => 'SES default configuration set', 'ok' => true, 'details' => 'Skipped (not configured).'];
        }

        $verified = (bool) Arr::get($identityData, 'VerifiedForSendingStatus', false);
        $steps[] = [
            'label' => 'SES verification status',
            'ok' => true,
            'details' => $verified
                ? 'Identity already verified for sending.'
                : 'Identity not yet verified. Apply DNS records and wait for SES verification.',
        ];

        return [
            'ok' => true,
            'steps' => $steps,
            'dns_records' => $dnsRecords,
        ];
    }

    /**
     * @return array{ok: bool, checks: array<int, array{key: string, label: string, ok: bool, details: string}>, dns_records: array<int, array{name: string, type: string, values: array<int, string>}>}
     */
    public function check(): array
    {
        $this->assertFeatureEnabled();

        $checks = [];
        $identity = $this->identity();
        $identityData = $this->api->getEmailIdentity($identity);

        $this->addCheck(
            $checks,
            'identity_exists',
            'SES identity exists',
            $identityData !== null,
            $identity
        );

        $dnsRecords = $identityData !== null ? $this->buildDnsRecords($identity, $identityData) : [];
        $verifiedForSending = (bool) Arr::get($identityData, 'VerifiedForSendingStatus', false);
        $this->addCheck(
            $checks,
            'identity_verified',
            'SES identity verified for sending',
            $verifiedForSending,
            $verifiedForSending ? 'Verified' : 'Pending verification'
        );

        if ($this->mailFromDomain()) {
            $mailFromStatus = (string) Arr::get($identityData, 'MailFromAttributes.MailFromDomainStatus', '');
            $this->addCheck(
                $checks,
                'mail_from_status',
                'SES MAIL FROM domain status',
                in_array($mailFromStatus, ['SUCCESS', 'PENDING'], true),
                $mailFromStatus !== '' ? $mailFromStatus : 'Not set'
            );
        }

        $ok = collect($checks)->every(fn (array $check): bool => $check['ok'] === true);

        return [
            'ok' => $ok,
            'checks' => $checks,
            'dns_records' => $dnsRecords,
        ];
    }

    /**
     * @param  array<int, array{name: string, type: string, values: array<int, string>}>  $dnsRecords
     * @param  array<int, array{label: string, ok: bool, details: string}>  $steps
     */
    protected function upsertDnsIfConfigured(string $identity, array $dnsRecords, array &$steps): void
    {
        $route53Enabled = (bool) config('mail-manager.ses_sns.sending.route53.enabled', false);
        $autoCreate = (bool) config('mail-manager.ses_sns.sending.route53.auto_create_records', false);

        if (! $route53Enabled || ! $autoCreate) {
            $steps[] = ['label' => 'Route53 DNS automation', 'ok' => true, 'details' => 'Skipped by configuration.'];

            return;
        }

        $hostedZoneId = (string) config('mail-manager.ses_sns.sending.route53.hosted_zone_id', '');
        if ($hostedZoneId === '') {
            $hostedZoneId = (string) $this->api->findHostedZoneIdByDomain($identityDomain = $this->identityDomainFromIdentity($identity));
            if ($hostedZoneId === '') {
                throw new RuntimeException('Route53 hosted zone not found for domain: '.$identityDomain);
            }
        }

        $ttl = (int) config('mail-manager.ses_sns.sending.route53.ttl', 300);
        foreach ($dnsRecords as $record) {
            $this->api->upsertRoute53Record(
                $hostedZoneId,
                $record['name'],
                $record['type'],
                $record['values'],
                $ttl
            );
        }

        $steps[] = ['label' => 'Route53 DNS automation', 'ok' => true, 'details' => 'Upserted '.count($dnsRecords).' record(s) in zone '.$hostedZoneId];
    }

    /**
     * @param  array<string, mixed>  $identityData
     * @return array<int, array{name: string, type: string, values: array<int, string>}>
     */
    protected function buildDnsRecords(string $identity, array $identityData): array
    {
        $records = [];
        $domain = $this->identityDomainFromIdentity($identity);

        $dkimTokens = (array) Arr::get($identityData, 'DkimAttributes.Tokens', []);
        foreach ($dkimTokens as $tokenRaw) {
            $token = trim((string) $tokenRaw);
            if ($token === '') {
                continue;
            }

            $records[] = [
                'name' => $token.'._domainkey.'.$domain,
                'type' => 'CNAME',
                'values' => [$token.'.dkim.amazonses.com'],
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array{name: string, type: string, values: array<int, string>}>
     */
    protected function buildMailFromDnsRecords(string $mailFromDomain): array
    {
        $region = (string) config('mail-manager.ses_sns.aws.region', 'eu-central-1');

        return [
            [
                'name' => $mailFromDomain,
                'type' => 'MX',
                'values' => ['10 feedback-smtp.'.$region.'.amazonses.com'],
            ],
            [
                'name' => $mailFromDomain,
                'type' => 'TXT',
                'values' => ['"v=spf1 include:amazonses.com -all"'],
            ],
        ];
    }

    protected function identity(): string
    {
        $domain = trim((string) config('mail-manager.ses_sns.sending.identity_domain', ''));
        $email = trim((string) config('mail-manager.ses_sns.sending.identity_email', ''));

        if ($domain !== '') {
            return $domain;
        }

        if ($email !== '') {
            return $email;
        }

        throw new RuntimeException('No SES identity configured. Set mail-manager.ses_sns.sending.identity_domain or identity_email.');
    }

    protected function identityDomainFromIdentity(string $identity): string
    {
        if (str_contains($identity, '@')) {
            return (string) substr(strrchr($identity, '@') ?: '', 1);
        }

        return $identity;
    }

    protected function mailFromDomain(): ?string
    {
        $value = trim((string) config('mail-manager.ses_sns.sending.mail_from_domain', ''));

        return $value !== '' ? $value : null;
    }

    protected function mailFromBehaviorOnMxFailure(): string
    {
        return (string) config('mail-manager.ses_sns.sending.mail_from_behavior_on_mx_failure', 'USE_DEFAULT_VALUE');
    }

    protected function configurationSetName(): ?string
    {
        $value = trim((string) config('mail-manager.ses_sns.configuration_set', ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, array{key: string, label: string, ok: bool, details: string}>  $checks
     */
    protected function addCheck(array &$checks, string $key, string $label, bool $ok, string $details): void
    {
        $checks[] = [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'details' => $details,
        ];
    }

    protected function assertFeatureEnabled(): void
    {
        if (! (bool) config('mail-manager.ses_sns.sending.enabled', false)) {
            throw new RuntimeException('mail-manager.ses_sns.sending.enabled is false.');
        }
    }
}
