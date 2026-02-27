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
            if (! $this->api->configurationSetExists($configurationSet)) {
                $this->api->createConfigurationSet($configurationSet);
                $steps[] = ['label' => 'SES configuration set', 'ok' => true, 'details' => 'Created: '.$configurationSet];
            } else {
                $steps[] = ['label' => 'SES configuration set', 'ok' => true, 'details' => 'Already exists: '.$configurationSet];
            }

            $this->api->putEmailIdentityConfigurationSetAttributes($identity, $configurationSet);
            $steps[] = ['label' => 'SES default configuration set', 'ok' => true, 'details' => 'Assigned to identity: '.$configurationSet];
        } else {
            $steps[] = ['label' => 'SES default configuration set', 'ok' => true, 'details' => 'Skipped (not configured).'];
        }

        $this->associateTenantIfConfigured($identity, $configurationSet, $steps);

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

        $mailFromAddress = trim((string) config('mail.from.address', ''));
        if ($mailFromAddress === '') {
            $this->addCheck(
                $checks,
                'mail_from_address_matches_identity',
                'MAIL_FROM_ADDRESS matches SES identity',
                false,
                'MAIL_FROM_ADDRESS is empty.'
            );
        } else {
            $mailFromAddressMatchesIdentity = $this->mailFromAddressMatchesIdentity($identity, $mailFromAddress);
            $this->addCheck(
                $checks,
                'mail_from_address_matches_identity',
                'MAIL_FROM_ADDRESS matches SES identity',
                $mailFromAddressMatchesIdentity,
                $mailFromAddressMatchesIdentity
                    ? sprintf('MAIL_FROM_ADDRESS "%s" matches identity "%s".', $mailFromAddress, $identity)
                    : sprintf('MAIL_FROM_ADDRESS "%s" does not match identity "%s".', $mailFromAddress, $identity)
            );
        }

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

        $tenantName = $this->tenantName();
        if ($tenantName !== null) {
            $tenantExists = $this->api->tenantExists($tenantName);
            $this->addCheck($checks, 'tenant_exists', 'SES tenant exists', $tenantExists, $tenantName);

            if ($tenantExists) {
                $region = (string) config('mail-manager.ses_sns.aws.region', 'eu-central-1');
                $accountId = $this->api->getCallerAccountId();
                $identityArn = sprintf('arn:aws:ses:%s:%s:identity/%s', $region, $accountId, $identity);
                $identityAssociated = $this->api->tenantHasResourceAssociation($tenantName, $identityArn);
                $this->addCheck(
                    $checks,
                    'tenant_identity_association',
                    'SES tenant has identity association',
                    $identityAssociated,
                    $identityAssociated ? $identityArn : 'Missing association for: '.$identityArn
                );

                $configurationSetName = $this->configurationSetName();
                if ($configurationSetName !== null && $configurationSetName !== '') {
                    $configurationSetArn = sprintf('arn:aws:ses:%s:%s:configuration-set/%s', $region, $accountId, $configurationSetName);
                    $configurationSetAssociated = $this->api->tenantHasResourceAssociation($tenantName, $configurationSetArn);
                    $this->addCheck(
                        $checks,
                        'tenant_configuration_set_association',
                        'SES tenant has configuration set association',
                        $configurationSetAssociated,
                        $configurationSetAssociated ? $configurationSetArn : 'Missing association for: '.$configurationSetArn
                    );
                }
            }
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

    protected function mailFromAddressMatchesIdentity(string $identity, string $mailFromAddress): bool
    {
        if (str_contains($identity, '@')) {
            return strtolower($mailFromAddress) === strtolower($identity);
        }

        if (! str_contains($mailFromAddress, '@')) {
            return false;
        }

        $mailFromAddressDomain = (string) substr(strrchr($mailFromAddress, '@') ?: '', 1);

        return strtolower($mailFromAddressDomain) === strtolower($identity);
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

    protected function tenantName(): ?string
    {
        $value = trim((string) config('mail-manager.ses_sns.tenant.name', ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, array{label: string, ok: bool, details: string}>  $steps
     */
    protected function associateTenantIfConfigured(string $identity, ?string $configurationSet, array &$steps): void
    {
        $tenantName = $this->tenantName();
        if ($tenantName === null) {
            $steps[] = ['label' => 'SES tenant', 'ok' => true, 'details' => 'Skipped (not configured).'];

            return;
        }

        if (! $this->api->tenantExists($tenantName)) {
            $this->api->createTenant($tenantName);
            $steps[] = ['label' => 'SES tenant', 'ok' => true, 'details' => 'Created: '.$tenantName];
        } else {
            $steps[] = ['label' => 'SES tenant', 'ok' => true, 'details' => 'Already exists: '.$tenantName];
        }

        $region = (string) config('mail-manager.ses_sns.aws.region', 'eu-central-1');
        $accountId = $this->api->getCallerAccountId();
        $identityArn = sprintf('arn:aws:ses:%s:%s:identity/%s', $region, $accountId, $identity);

        if (! $this->api->tenantHasResourceAssociation($tenantName, $identityArn)) {
            $this->api->associateTenantResource($tenantName, $identityArn);
            $steps[] = ['label' => 'SES tenant identity association', 'ok' => true, 'details' => 'Associated: '.$identityArn];
        } else {
            $steps[] = ['label' => 'SES tenant identity association', 'ok' => true, 'details' => 'Already associated: '.$identityArn];
        }

        if ($configurationSet === null || $configurationSet === '') {
            $steps[] = ['label' => 'SES tenant configuration set association', 'ok' => true, 'details' => 'Skipped (configuration set not configured).'];

            return;
        }

        $configurationSetArn = sprintf('arn:aws:ses:%s:%s:configuration-set/%s', $region, $accountId, $configurationSet);
        if (! $this->api->tenantHasResourceAssociation($tenantName, $configurationSetArn)) {
            $this->api->associateTenantResource($tenantName, $configurationSetArn);
            $steps[] = ['label' => 'SES tenant configuration set association', 'ok' => true, 'details' => 'Associated: '.$configurationSetArn];
        } else {
            $steps[] = ['label' => 'SES tenant configuration set association', 'ok' => true, 'details' => 'Already associated: '.$configurationSetArn];
        }
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
}
