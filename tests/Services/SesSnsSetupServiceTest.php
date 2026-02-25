<?php

use Topoff\MailManager\Contracts\SesSnsProvisioningApi;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;

it('provisions missing ses/sns resources and returns green status', function () {
    config()->set('mail-manager.ses_sns.enabled', true);
    config()->set('mail-manager.ses_sns.aws.region', 'eu-central-1');
    config()->set('mail-manager.ses_sns.topic_name', 'mail-manager-events');
    config()->set('mail-manager.ses_sns.configuration_set', 'mail-manager-tracking');
    config()->set('mail-manager.ses_sns.event_destination', 'mail-manager-sns');
    config()->set('mail-manager.ses_sns.callback_endpoint', 'https://mail-manager-demo.ngrok-free.app/email/sns');
    config()->set('mail-manager.ses_sns.event_types', ['SEND', 'BOUNCE', 'COMPLAINT', 'DELIVERY']);

    $fake = new class implements SesSnsProvisioningApi
    {
        public string $accountId = '123456789012';
        public ?string $topicArn = null;
        public array $topicAttributes = [];
        public array $subscriptions = [];
        public bool $configurationSetExists = false;
        public ?array $eventDestination = null;

        public function getCallerAccountId(): string
        {
            return $this->accountId;
        }

        public function findTopicArnByName(string $topicName): ?string
        {
            return $this->topicArn;
        }

        public function createTopic(string $topicName): string
        {
            $this->topicArn = 'arn:aws:sns:eu-central-1:123456789012:'.$topicName;

            return $this->topicArn;
        }

        public function getTopicAttributes(string $topicArn): array
        {
            return $this->topicAttributes;
        }

        public function setTopicPolicy(string $topicArn, string $policyJson): void
        {
            $this->topicAttributes['Policy'] = $policyJson;
        }

        public function hasHttpsSubscription(string $topicArn, string $endpoint): bool
        {
            return in_array($endpoint, $this->subscriptions, true);
        }

        public function findHttpsSubscriptionArn(string $topicArn, string $endpoint): ?string
        {
            return in_array($endpoint, $this->subscriptions, true) ? 'arn:aws:sns:eu-central-1:123456789012:sub/example' : null;
        }

        public function subscribeHttps(string $topicArn, string $endpoint): void
        {
            $this->subscriptions[] = $endpoint;
        }

        public function unsubscribe(string $subscriptionArn): void
        {
            $this->subscriptions = [];
        }

        public function deleteTopic(string $topicArn): void
        {
            $this->topicArn = null;
        }

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
            return $this->eventDestination;
        }

        public function upsertEventDestination(
            string $configurationSetName,
            string $eventDestinationName,
            string $topicArn,
            array $eventTypes,
            bool $enabled = true,
        ): void {
            $this->eventDestination = [
                'Name' => $eventDestinationName,
                'Enabled' => $enabled,
                'MatchingEventTypes' => $eventTypes,
                'SnsDestination' => ['TopicArn' => $topicArn],
            ];
        }

        public function deleteEventDestination(string $configurationSetName, string $eventDestinationName): void
        {
            $this->eventDestination = null;
        }

        public function deleteConfigurationSet(string $configurationSetName): void
        {
            $this->configurationSetExists = false;
        }
    };

    $service = new SesSnsSetupService($fake);
    $setupResult = $service->setup();
    $status = $service->check();

    expect($setupResult['ok'])->toBeTrue()
        ->and($status['ok'])->toBeTrue()
        ->and($fake->topicArn)->not->toBeNull()
        ->and($fake->configurationSetExists)->toBeTrue()
        ->and($fake->eventDestination)->not->toBeNull()
        ->and($fake->subscriptions)->toContain('https://mail-manager-demo.ngrok-free.app/email/sns');
});

it('returns failing checks when topic is missing', function () {
    config()->set('mail-manager.ses_sns.enabled', true);
    config()->set('mail-manager.ses_sns.topic_name', 'mail-manager-events');
    config()->set('mail-manager.ses_sns.configuration_set', 'mail-manager-tracking');
    config()->set('mail-manager.ses_sns.event_destination', 'mail-manager-sns');
    config()->set('mail-manager.ses_sns.callback_endpoint', 'https://backend.example.test/email/sns');

    $fake = new class implements SesSnsProvisioningApi
    {
        public function getCallerAccountId(): string
        {
            return '123456789012';
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

        public function upsertEventDestination(
            string $configurationSetName,
            string $eventDestinationName,
            string $topicArn,
            array $eventTypes,
            bool $enabled = true,
        ): void {}

        public function deleteEventDestination(string $configurationSetName, string $eventDestinationName): void {}

        public function deleteConfigurationSet(string $configurationSetName): void {}
    };

    $service = new SesSnsSetupService($fake);
    $status = $service->check();

    expect($status['ok'])->toBeFalse()
        ->and(collect($status['checks'])->firstWhere('key', 'sns_topic')['ok'])->toBeFalse();
});

it('tears down existing ses/sns resources', function () {
    config()->set('mail-manager.ses_sns.enabled', true);
    config()->set('mail-manager.ses_sns.topic_name', 'mail-manager-events');
    config()->set('mail-manager.ses_sns.configuration_set', 'mail-manager-tracking');
    config()->set('mail-manager.ses_sns.event_destination', 'mail-manager-sns');
    config()->set('mail-manager.ses_sns.callback_endpoint', 'https://backend.example.test/email/sns');

    $fake = new class implements SesSnsProvisioningApi
    {
        public ?string $topicArn = 'arn:aws:sns:eu-central-1:123456789012:mail-manager-events';
        public bool $configurationSetExists = true;
        public ?array $eventDestination = ['Name' => 'mail-manager-sns'];
        public array $subscriptions = ['https://backend.example.test/email/sns'];

        public function getCallerAccountId(): string { return '123456789012'; }
        public function findTopicArnByName(string $topicName): ?string { return $this->topicArn; }
        public function createTopic(string $topicName): string { return $this->topicArn ?? ''; }
        public function getTopicAttributes(string $topicArn): array { return []; }
        public function setTopicPolicy(string $topicArn, string $policyJson): void {}
        public function hasHttpsSubscription(string $topicArn, string $endpoint): bool { return in_array($endpoint, $this->subscriptions, true); }
        public function findHttpsSubscriptionArn(string $topicArn, string $endpoint): ?string { return in_array($endpoint, $this->subscriptions, true) ? 'arn:sub' : null; }
        public function subscribeHttps(string $topicArn, string $endpoint): void {}
        public function unsubscribe(string $subscriptionArn): void { $this->subscriptions = []; }
        public function deleteTopic(string $topicArn): void { $this->topicArn = null; }
        public function configurationSetExists(string $configurationSetName): bool { return $this->configurationSetExists; }
        public function createConfigurationSet(string $configurationSetName): void {}
        public function getEventDestination(string $configurationSetName, string $eventDestinationName): ?array { return $this->eventDestination; }
        public function upsertEventDestination(string $configurationSetName, string $eventDestinationName, string $topicArn, array $eventTypes, bool $enabled = true): void {}
        public function deleteEventDestination(string $configurationSetName, string $eventDestinationName): void { $this->eventDestination = null; }
        public function deleteConfigurationSet(string $configurationSetName): void { $this->configurationSetExists = false; }
    };

    $service = new SesSnsSetupService($fake);
    $result = $service->teardown();

    expect($result['ok'])->toBeTrue()
        ->and($fake->subscriptions)->toBe([])
        ->and($fake->eventDestination)->toBeNull()
        ->and($fake->configurationSetExists)->toBeFalse()
        ->and($fake->topicArn)->toBeNull();
});
