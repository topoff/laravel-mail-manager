<?php

namespace Topoff\MailManager\Services\SesSns;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Topoff\MailManager\Contracts\SesSnsProvisioningApi;
use Throwable;

class SesSnsSetupService
{
    public function __construct(protected SesSnsProvisioningApi $api) {}

    /**
     * @return array{ok: bool, steps: array<int, array{label: string, ok: bool, details: string}>, status: array<string, mixed>}
     */
    public function setup(): array
    {
        $this->assertFeatureEnabled();

        $steps = [];

        $accountId = $this->accountId();
        $topicArn = $this->ensureTopic($steps);
        $this->ensureTopicPolicy($topicArn, $accountId, $steps);
        $this->ensureHttpsSubscription($topicArn, $steps);
        $this->ensureConfigurationSet($steps);
        $this->ensureEventDestination($topicArn, $steps);

        $status = $this->check();

        return [
            'ok' => $status['ok'],
            'steps' => $steps,
            'status' => $status,
        ];
    }

    /**
     * @return array{ok: bool, configuration: array<string, mixed>, checks: array<int, array{key: string, label: string, ok: bool, details: string}>, aws_console: array<string, string>}
     */
    public function check(): array
    {
        $this->assertFeatureEnabled();

        $configurationSet = $this->configurationSetName();
        $destinationName = $this->eventDestinationName();
        $topicArn = $this->resolveTopicArn();
        $endpoint = $this->callbackEndpoint();
        $eventTypes = $this->eventTypes();

        $checks = [];
        $this->addCheck($checks, 'sns_topic', 'SNS topic exists', $topicArn !== '', $topicArn !== '' ? $topicArn : 'Topic not found');

        $topicAttributes = [];
        if ($topicArn !== '') {
            $topicAttributes = $this->api->getTopicAttributes($topicArn);
            $policyRaw = (string) ($topicAttributes['Policy'] ?? '');
            $policyAllowsSes = $this->topicPolicyAllowsSesPublish($policyRaw);
            $this->addCheck($checks, 'sns_policy', 'SNS topic policy allows SES publish', $policyAllowsSes, $policyAllowsSes ? 'Policy contains ses.amazonaws.com publish statement.' : 'Missing SES publish permission in policy.');

            if ($endpoint !== '') {
                $subscriptionExists = $this->api->hasHttpsSubscription($topicArn, $endpoint);
                $this->addCheck(
                    $checks,
                    'sns_subscription',
                    'HTTPS subscription exists for callback endpoint',
                    $subscriptionExists,
                    $subscriptionExists ? $endpoint : 'Missing HTTPS subscription for: '.$endpoint
                );
            } else {
                $this->addCheck($checks, 'sns_subscription', 'HTTPS subscription endpoint configured', false, 'Callback endpoint is empty.');
            }
        } else {
            $this->addCheck($checks, 'sns_policy', 'SNS topic policy allows SES publish', false, 'Topic missing.');
            $this->addCheck($checks, 'sns_subscription', 'HTTPS subscription exists for callback endpoint', false, 'Topic missing.');
        }

        $configurationSetExists = $this->api->configurationSetExists($configurationSet);
        $this->addCheck($checks, 'ses_configuration_set', 'SES configuration set exists', $configurationSetExists, $configurationSet);

        if ($configurationSetExists) {
            $eventDestination = $this->api->getEventDestination($configurationSet, $destinationName);
            $destinationExists = $eventDestination !== null;
            $this->addCheck($checks, 'ses_destination_exists', 'SES event destination exists', $destinationExists, $destinationExists ? $destinationName : 'Missing destination');

            if ($destinationExists) {
                $destinationTopicArn = (string) Arr::get($eventDestination, 'SnsDestination.TopicArn', '');
                $enabled = (bool) Arr::get($eventDestination, 'Enabled', false);
                $configuredEventTypes = array_map('strtoupper', (array) Arr::get($eventDestination, 'MatchingEventTypes', []));
                $missingEventTypes = array_values(array_diff($eventTypes, $configuredEventTypes));

                $this->addCheck($checks, 'ses_destination_topic', 'SES destination points to SNS topic', $topicArn !== '' && $destinationTopicArn === $topicArn, $destinationTopicArn);
                $this->addCheck($checks, 'ses_destination_enabled', 'SES destination is enabled', $enabled, $enabled ? 'Enabled' : 'Disabled');
                $this->addCheck(
                    $checks,
                    'ses_destination_events',
                    'SES destination has required event types',
                    $missingEventTypes === [],
                    $missingEventTypes === [] ? implode(', ', $configuredEventTypes) : 'Missing: '.implode(', ', $missingEventTypes)
                );
            } else {
                $this->addCheck($checks, 'ses_destination_topic', 'SES destination points to SNS topic', false, 'Destination missing.');
                $this->addCheck($checks, 'ses_destination_enabled', 'SES destination is enabled', false, 'Destination missing.');
                $this->addCheck($checks, 'ses_destination_events', 'SES destination has required event types', false, 'Destination missing.');
            }
        } else {
            $this->addCheck($checks, 'ses_destination_exists', 'SES event destination exists', false, 'Configuration set missing.');
            $this->addCheck($checks, 'ses_destination_topic', 'SES destination points to SNS topic', false, 'Configuration set missing.');
            $this->addCheck($checks, 'ses_destination_enabled', 'SES destination is enabled', false, 'Configuration set missing.');
            $this->addCheck($checks, 'ses_destination_events', 'SES destination has required event types', false, 'Configuration set missing.');
        }

        $ok = collect($checks)->every(fn (array $check): bool => $check['ok'] === true);
        $region = (string) config('mail-manager.ses_sns.aws.region', 'eu-central-1');
        $consoleRegion = $region !== '' ? $region : 'eu-central-1';

        return [
            'ok' => $ok,
            'configuration' => [
                'region' => $region,
                'configuration_set' => $configurationSet,
                'event_destination' => $destinationName,
                'topic_arn' => $topicArn,
                'topic_name' => (string) config('mail-manager.ses_sns.topic_name', ''),
                'callback_endpoint' => $endpoint,
                'event_types' => $eventTypes,
            ],
            'checks' => $checks,
            'aws_console' => [
                'ses_configuration_sets' => 'https://'.$consoleRegion.'.console.aws.amazon.com/sesv2/home?region='.$consoleRegion.'#/configuration-sets',
                'sns_topics' => 'https://'.$consoleRegion.'.console.aws.amazon.com/sns/v3/home?region='.$consoleRegion.'#/topics',
                'sns_subscriptions' => 'https://'.$consoleRegion.'.console.aws.amazon.com/sns/v3/home?region='.$consoleRegion.'#/subscriptions',
            ],
        ];
    }

    /**
     * @return array{ok: bool, steps: array<int, array{label: string, ok: bool, details: string}>}
     */
    public function teardown(): array
    {
        $this->assertFeatureEnabled();

        $steps = [];
        $topicArn = $this->resolveTopicArn();
        $endpoint = $this->callbackEndpoint();
        $configurationSet = $this->configurationSetName();
        $eventDestination = $this->eventDestinationName();

        if ($topicArn !== '' && $endpoint !== '') {
            $subscriptionArn = $this->api->findHttpsSubscriptionArn($topicArn, $endpoint);
            if ($subscriptionArn !== null && $subscriptionArn !== '' && $subscriptionArn !== 'PendingConfirmation') {
                $this->api->unsubscribe($subscriptionArn);
                $steps[] = ['label' => 'SNS HTTPS subscription', 'ok' => true, 'details' => 'Removed: '.$subscriptionArn];
            } else {
                $steps[] = ['label' => 'SNS HTTPS subscription', 'ok' => true, 'details' => 'Nothing to remove.'];
            }
        } else {
            $steps[] = ['label' => 'SNS HTTPS subscription', 'ok' => true, 'details' => 'Skipped: missing topic or endpoint.'];
        }

        try {
            if ($this->api->configurationSetExists($configurationSet)) {
                $eventDestinationData = $this->api->getEventDestination($configurationSet, $eventDestination);
                if ($eventDestinationData !== null) {
                    $this->api->deleteEventDestination($configurationSet, $eventDestination);
                    $steps[] = ['label' => 'SES event destination', 'ok' => true, 'details' => 'Removed: '.$eventDestination];
                } else {
                    $steps[] = ['label' => 'SES event destination', 'ok' => true, 'details' => 'Nothing to remove.'];
                }

                $this->api->deleteConfigurationSet($configurationSet);
                $steps[] = ['label' => 'SES configuration set', 'ok' => true, 'details' => 'Removed: '.$configurationSet];
            } else {
                $steps[] = ['label' => 'SES event destination', 'ok' => true, 'details' => 'Skipped: configuration set missing.'];
                $steps[] = ['label' => 'SES configuration set', 'ok' => true, 'details' => 'Nothing to remove.'];
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to remove SES resources: '.$e->getMessage(), previous: $e);
        }

        if ($topicArn !== '') {
            try {
                $this->api->deleteTopic($topicArn);
                $steps[] = ['label' => 'SNS topic', 'ok' => true, 'details' => 'Removed: '.$topicArn];
            } catch (Throwable $e) {
                throw new RuntimeException('Failed to remove SNS topic: '.$e->getMessage(), previous: $e);
            }
        } else {
            $steps[] = ['label' => 'SNS topic', 'ok' => true, 'details' => 'Nothing to remove.'];
        }

        return [
            'ok' => true,
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<int, array{label: string, ok: bool, details: string}>  $steps
     */
    protected function ensureTopic(array &$steps): string
    {
        $topicArn = $this->resolveTopicArn();

        if ($topicArn !== '') {
            $steps[] = ['label' => 'SNS topic', 'ok' => true, 'details' => 'Using existing topic: '.$topicArn];

            return $topicArn;
        }

        if (! (bool) config('mail-manager.ses_sns.create_topic_if_missing', true)) {
            throw new RuntimeException('SNS topic does not exist and create_topic_if_missing is false.');
        }

        $topicName = (string) config('mail-manager.ses_sns.topic_name', '');
        if ($topicName === '') {
            throw new RuntimeException('mail-manager.ses_sns.topic_name is empty.');
        }

        $createdArn = $this->api->createTopic($topicName);
        if ($createdArn === '') {
            throw new RuntimeException('Failed to create SNS topic.');
        }

        $steps[] = ['label' => 'SNS topic', 'ok' => true, 'details' => 'Created topic: '.$createdArn];

        return $createdArn;
    }

    /**
     * @param  array<int, array{label: string, ok: bool, details: string}>  $steps
     */
    protected function ensureTopicPolicy(string $topicArn, string $accountId, array &$steps): void
    {
        if (! (bool) config('mail-manager.ses_sns.set_topic_policy', true)) {
            $steps[] = ['label' => 'SNS topic policy', 'ok' => true, 'details' => 'Skipped by configuration.'];

            return;
        }

        $policy = json_encode($this->buildTopicPolicy($topicArn, $accountId), JSON_THROW_ON_ERROR);
        $this->api->setTopicPolicy($topicArn, $policy);

        $steps[] = ['label' => 'SNS topic policy', 'ok' => true, 'details' => 'Policy updated for SES publish permissions.'];
    }

    /**
     * @param  array<int, array{label: string, ok: bool, details: string}>  $steps
     */
    protected function ensureHttpsSubscription(string $topicArn, array &$steps): void
    {
        if (! (bool) config('mail-manager.ses_sns.create_https_subscription_if_missing', true)) {
            $steps[] = ['label' => 'SNS HTTPS subscription', 'ok' => true, 'details' => 'Skipped by configuration.'];

            return;
        }

        $endpoint = $this->callbackEndpoint();
        if ($endpoint === '') {
            throw new RuntimeException('Callback endpoint is empty. Configure mail-manager.ses_sns.callback_endpoint or APP_URL.');
        }

        if (! $this->isPublicHttpsEndpoint($endpoint)) {
            throw new RuntimeException(
                'Callback endpoint is not publicly reachable via HTTPS for AWS SNS: '.$endpoint.
                '. Use a public HTTPS URL in mail-manager.ses_sns.callback_endpoint or set create_https_subscription_if_missing=false.'
            );
        }

        if ($this->api->hasHttpsSubscription($topicArn, $endpoint)) {
            $steps[] = ['label' => 'SNS HTTPS subscription', 'ok' => true, 'details' => 'Already subscribed: '.$endpoint];

            return;
        }

        try {
            $this->api->subscribeHttps($topicArn, $endpoint);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'SNS could not subscribe endpoint "'.$endpoint.'". AWS requires a publicly reachable HTTPS endpoint. Original error: '.$e->getMessage(),
                previous: $e
            );
        }

        $steps[] = ['label' => 'SNS HTTPS subscription', 'ok' => true, 'details' => 'Subscription requested: '.$endpoint];
    }

    /**
     * @param  array<int, array{label: string, ok: bool, details: string}>  $steps
     */
    protected function ensureConfigurationSet(array &$steps): void
    {
        $name = $this->configurationSetName();
        if ($this->api->configurationSetExists($name)) {
            $steps[] = ['label' => 'SES configuration set', 'ok' => true, 'details' => 'Already exists: '.$name];

            return;
        }

        $this->api->createConfigurationSet($name);
        $steps[] = ['label' => 'SES configuration set', 'ok' => true, 'details' => 'Created: '.$name];
    }

    /**
     * @param  array<int, array{label: string, ok: bool, details: string}>  $steps
     */
    protected function ensureEventDestination(string $topicArn, array &$steps): void
    {
        $this->api->upsertEventDestination(
            $this->configurationSetName(),
            $this->eventDestinationName(),
            $topicArn,
            $this->eventTypes(),
            (bool) config('mail-manager.ses_sns.enable_event_destination', true),
        );

        $steps[] = ['label' => 'SES event destination', 'ok' => true, 'details' => 'Upserted with SNS topic: '.$topicArn];
    }

    protected function resolveTopicArn(): string
    {
        $configuredTopicArn = (string) config('mail-manager.ses_sns.topic_arn', '');
        if ($configuredTopicArn !== '') {
            return $configuredTopicArn;
        }

        $topicName = (string) config('mail-manager.ses_sns.topic_name', '');
        if ($topicName === '') {
            return '';
        }

        return (string) $this->api->findTopicArnByName($topicName);
    }

    protected function accountId(): string
    {
        $configuredAccountId = (string) config('mail-manager.ses_sns.aws.account_id', '');
        if ($configuredAccountId !== '') {
            return $configuredAccountId;
        }

        return $this->api->getCallerAccountId();
    }

    protected function callbackEndpoint(): string
    {
        $configuredEndpoint = (string) config('mail-manager.ses_sns.callback_endpoint', '');
        if ($configuredEndpoint !== '') {
            return $configuredEndpoint;
        }

        if (! Route::has('mail-manager.tracking.sns')) {
            return '';
        }

        return route('mail-manager.tracking.sns');
    }

    protected function configurationSetName(): string
    {
        return (string) config('mail-manager.ses_sns.configuration_set', 'mail-manager-tracking');
    }

    protected function eventDestinationName(): string
    {
        return (string) config('mail-manager.ses_sns.event_destination', 'mail-manager-sns');
    }

    /**
     * @return array<int, string>
     */
    protected function eventTypes(): array
    {
        $eventTypes = (array) config('mail-manager.ses_sns.event_types', ['SEND', 'REJECT', 'BOUNCE', 'COMPLAINT', 'DELIVERY']);

        return array_values(array_unique(array_map(static fn (mixed $type): string => strtoupper((string) $type), $eventTypes)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTopicPolicy(string $topicArn, string $accountId): array
    {
        $region = (string) config('mail-manager.ses_sns.aws.region', 'eu-central-1');
        $configurationSetName = $this->configurationSetName();
        $configurationSetArn = "arn:aws:ses:{$region}:{$accountId}:configuration-set/{$configurationSetName}";

        return [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'AllowSesPublish',
                    'Effect' => 'Allow',
                    'Principal' => ['Service' => 'ses.amazonaws.com'],
                    'Action' => 'SNS:Publish',
                    'Resource' => $topicArn,
                    'Condition' => [
                        'StringEquals' => ['AWS:SourceAccount' => $accountId],
                        'ArnLike' => ['AWS:SourceArn' => $configurationSetArn],
                    ],
                ],
                [
                    'Sid' => 'AllowAccountAdministration',
                    'Effect' => 'Allow',
                    'Principal' => ['AWS' => "arn:aws:iam::{$accountId}:root"],
                    'Action' => [
                        'SNS:GetTopicAttributes',
                        'SNS:SetTopicAttributes',
                        'SNS:Subscribe',
                        'SNS:ListSubscriptionsByTopic',
                        'SNS:Publish',
                    ],
                    'Resource' => $topicArn,
                ],
            ],
        ];
    }

    protected function topicPolicyAllowsSesPublish(string $policyRaw): bool
    {
        if ($policyRaw === '') {
            return false;
        }

        $policy = json_decode($policyRaw, true);
        if (! is_array($policy)) {
            return false;
        }

        $statements = $policy['Statement'] ?? [];
        if (! is_array($statements)) {
            return false;
        }

        foreach ($statements as $statement) {
            if (! is_array($statement)) {
                continue;
            }

            $servicePrincipal = data_get($statement, 'Principal.Service');
            $action = data_get($statement, 'Action');

            if ($servicePrincipal === 'ses.amazonaws.com' && ($action === 'SNS:Publish' || (is_array($action) && in_array('SNS:Publish', $action, true)))) {
                return true;
            }
        }

        return false;
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
        if (! (bool) config('mail-manager.ses_sns.enabled', false)) {
            throw new RuntimeException('mail-manager.ses_sns.enabled is false.');
        }
    }

    protected function isPublicHttpsEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (str_ends_with($host, '.test') || str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            return false;
        }

        return true;
    }
}
