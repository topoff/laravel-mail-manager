<?php

namespace Topoff\MailManager\Contracts;

interface SesSnsProvisioningApi
{
    public function getCallerAccountId(): string;

    public function findTopicArnByName(string $topicName): ?string;

    public function createTopic(string $topicName): string;

    /**
     * @return array<string, mixed>
     */
    public function getTopicAttributes(string $topicArn): array;

    public function setTopicPolicy(string $topicArn, string $policyJson): void;

    public function hasHttpsSubscription(string $topicArn, string $endpoint): bool;

    public function findHttpsSubscriptionArn(string $topicArn, string $endpoint): ?string;

    public function subscribeHttps(string $topicArn, string $endpoint): void;

    public function unsubscribe(string $subscriptionArn): void;

    public function deleteTopic(string $topicArn): void;

    public function configurationSetExists(string $configurationSetName): bool;

    public function createConfigurationSet(string $configurationSetName): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getEventDestination(string $configurationSetName, string $eventDestinationName): ?array;

    /**
     * @param  array<int, string>  $eventTypes
     */
    public function upsertEventDestination(
        string $configurationSetName,
        string $eventDestinationName,
        string $topicArn,
        array $eventTypes,
        bool $enabled = true,
    ): void;

    public function deleteEventDestination(string $configurationSetName, string $eventDestinationName): void;

    public function deleteConfigurationSet(string $configurationSetName): void;
}
