<?php

use Topoff\MailManager\Contracts\SesSnsProvisioningApi;
use Topoff\MailManager\Services\SesSns\SesSendingSetupService;

it('creates ses domain identity and returns required dns records', function () {
    config()->set('mail-manager.ses_sns.sending.enabled', true);
    config()->set('mail-manager.ses_sns.sending.identity_domain', 'example.com');
    config()->set('mail-manager.ses_sns.sending.mail_from_domain', 'mail.example.com');
    config()->set('mail-manager.ses_sns.aws.region', 'eu-central-1');

    $fake = new class implements SesSnsProvisioningApi
    {
        public bool $identityExists = false;

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

        public function putEmailIdentityConfigurationSetAttributes(string $identity, string $configurationSetName): void {}

        public function findHostedZoneIdByDomain(string $domain): ?string
        {
            return null;
        }

        public function upsertRoute53Record(string $hostedZoneId, string $recordName, string $recordType, array $values, int $ttl = 300): void {}
    };

    $service = new SesSendingSetupService($fake);
    $result = $service->setup();

    expect($result['ok'])->toBeTrue()
        ->and(count($result['dns_records']))->toBe(5);
});
