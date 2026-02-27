<?php

use Topoff\MailManager\Contracts\SesSnsProvisioningApi;
use Topoff\MailManager\Services\SesSns\SesSendingSetupService;

it('creates ses domain identity and returns required dns records', function () {
    config()->set('mail-manager.ses_sns.sending.enabled', true);
    config()->set('mail-manager.ses_sns.sending.identity_domain', 'example.com');
    config()->set('mail-manager.ses_sns.sending.mail_from_domain', 'mail.example.com');
    config()->set('mail-manager.ses_sns.aws.region', 'eu-central-1');
    config()->set('mail-manager.ses_sns.configuration_set', 'mail-manager-tracking');
    config()->set('mail-manager.ses_sns.tenant.name', 'topofferten');

    $fake = new class implements SesSnsProvisioningApi
    {
        public bool $identityExists = false;

        public bool $configurationSetExists = false;

        public ?array $assignedConfigurationSet = null;

        public bool $tenantExists = false;

        /** @var array<int, string> */
        public array $associatedResources = [];

        public array $identityData = [
            'VerifiedForSendingStatus' => false,
            'DkimAttributes' => [
                'Tokens' => ['aaa', 'bbb', 'ccc'],
            ],
            'MailFromAttributes' => [
                'MailFromDomainStatus' => 'PENDING',
            ],
        ];

        public function getCallerAccountId(): string
        {
            return '123';
        }

        public function findTopicArnByName(string $topicName): ?string
        {
            return null;
        }

        public function createTopic(string $topicName): string
        {
            return '';
        }

        public function getTopicAttributes(string $topicArn): array
        {
            return [];
        }

        public function setTopicPolicy(string $topicArn, string $policyJson): void {}

        public function hasHttpsSubscription(string $topicArn, string $endpoint): bool
        {
            return false;
        }

        public function findHttpsSubscriptionArn(string $topicArn, string $endpoint): ?string
        {
            return null;
        }

        public function subscribeHttps(string $topicArn, string $endpoint): void {}

        public function unsubscribe(string $subscriptionArn): void {}

        public function deleteTopic(string $topicArn): void {}

        public function configurationSetExists(string $configurationSetName): bool
        {
            return $this->configurationSetExists;
        }

        public function createConfigurationSet(string $configurationSetName): void
        {
            $this->configurationSetExists = true;
        }

        public function getEventDestination(string $configurationSetName, string $eventDestinationName): ?array
        {
            return null;
        }

        public function upsertEventDestination(string $configurationSetName, string $eventDestinationName, string $topicArn, array $eventTypes, bool $enabled = true): void {}

        public function deleteEventDestination(string $configurationSetName, string $eventDestinationName): void {}

        public function deleteConfigurationSet(string $configurationSetName): void {}

        public function tenantExists(string $tenantName): bool
        {
            return $this->tenantExists;
        }

        public function createTenant(string $tenantName): void
        {
            $this->tenantExists = true;
        }

        public function tenantHasResourceAssociation(string $tenantName, string $resourceArn): bool
        {
            return in_array($resourceArn, $this->associatedResources, true);
        }

        public function associateTenantResource(string $tenantName, string $resourceArn): void
        {
            $this->associatedResources[] = $resourceArn;
        }

        public function disassociateTenantResource(string $tenantName, string $resourceArn): void
        {
            $this->associatedResources = array_values(array_filter(
                $this->associatedResources,
                static fn (string $arn): bool => $arn !== $resourceArn
            ));
        }

        public function getEmailIdentity(string $identity): ?array
        {
            return $this->identityExists ? $this->identityData : null;
        }

        public function createEmailIdentity(string $identity): array
        {
            $this->identityExists = true;

            return [];
        }

        public function putEmailIdentityMailFromAttributes(string $identity, string $mailFromDomain, string $behaviorOnMxFailure = 'USE_DEFAULT_VALUE'): void {}

        public function putEmailIdentityConfigurationSetAttributes(string $identity, string $configurationSetName): void
        {
            $this->assignedConfigurationSet = [
                'identity' => $identity,
                'configuration_set' => $configurationSetName,
            ];
        }

        public function findHostedZoneIdByDomain(string $domain): ?string
        {
            return null;
        }

        public function upsertRoute53Record(string $hostedZoneId, string $recordName, string $recordType, array $values, int $ttl = 300): void {}
    };

    $service = new SesSendingSetupService($fake);
    $result = $service->setup();

    expect($result['ok'])->toBeTrue()
        ->and(count($result['dns_records']))->toBe(5)
        ->and($fake->configurationSetExists)->toBeTrue()
        ->and($fake->assignedConfigurationSet)->toBe([
            'identity' => 'example.com',
            'configuration_set' => 'mail-manager-tracking',
        ])
        ->and($fake->tenantExists)->toBeTrue()
        ->and($fake->associatedResources)->toContain(
            'arn:aws:ses:eu-central-1:123:identity/example.com',
            'arn:aws:ses:eu-central-1:123:configuration-set/mail-manager-tracking',
        );
});

it('checks if MAIL_FROM_ADDRESS matches ses identity', function () {
    config()->set('mail-manager.ses_sns.sending.identity_email', 'sender@example.com');
    config()->set('mail.from.address', 'sender@example.com');

    $fake = new class implements SesSnsProvisioningApi
    {
        public function getCallerAccountId(): string
        {
            return '123';
        }

        public function findTopicArnByName(string $topicName): ?string
        {
            return null;
        }

        public function createTopic(string $topicName): string
        {
            return '';
        }

        public function getTopicAttributes(string $topicArn): array
        {
            return [];
        }

        public function setTopicPolicy(string $topicArn, string $policyJson): void {}

        public function hasHttpsSubscription(string $topicArn, string $endpoint): bool
        {
            return false;
        }

        public function findHttpsSubscriptionArn(string $topicArn, string $endpoint): ?string
        {
            return null;
        }

        public function subscribeHttps(string $topicArn, string $endpoint): void {}

        public function unsubscribe(string $subscriptionArn): void {}

        public function deleteTopic(string $topicArn): void {}

        public function configurationSetExists(string $configurationSetName): bool
        {
            return false;
        }

        public function createConfigurationSet(string $configurationSetName): void {}

        public function getEventDestination(string $configurationSetName, string $eventDestinationName): ?array
        {
            return null;
        }

        public function upsertEventDestination(string $configurationSetName, string $eventDestinationName, string $topicArn, array $eventTypes, bool $enabled = true): void {}

        public function deleteEventDestination(string $configurationSetName, string $eventDestinationName): void {}

        public function deleteConfigurationSet(string $configurationSetName): void {}

        public function tenantExists(string $tenantName): bool
        {
            return false;
        }

        public function createTenant(string $tenantName): void {}

        public function tenantHasResourceAssociation(string $tenantName, string $resourceArn): bool
        {
            return false;
        }

        public function associateTenantResource(string $tenantName, string $resourceArn): void {}

        public function disassociateTenantResource(string $tenantName, string $resourceArn): void {}

        public function getEmailIdentity(string $identity): ?array
        {
            return ['VerifiedForSendingStatus' => true];
        }

        public function createEmailIdentity(string $identity): array
        {
            return [];
        }

        public function putEmailIdentityMailFromAttributes(string $identity, string $mailFromDomain, string $behaviorOnMxFailure = 'USE_DEFAULT_VALUE'): void {}

        public function putEmailIdentityConfigurationSetAttributes(string $identity, string $configurationSetName): void {}

        public function findHostedZoneIdByDomain(string $domain): ?string
        {
            return null;
        }

        public function upsertRoute53Record(string $hostedZoneId, string $recordName, string $recordType, array $values, int $ttl = 300): void {}
    };

    $service = new SesSendingSetupService($fake);
    $result = $service->check();

    expect($result['ok'])->toBeTrue()
        ->and(collect($result['checks'])->firstWhere('key', 'mail_from_address_matches_identity')['ok'])->toBeTrue();
});
