# Notification Service Metrics

| Metric Name | Description | Units | Labels |
| --- | --- | --- | --- |
| `notify_success_total` | Count of notifications processed successfully. | count | none |
| `notify_failed_total` | Count of notifications that failed processing. | count | none |
| `dlq_push_total` | Number of messages pushed to the dead letter queue. | count | none |
| `notify_retry_total` | The total number of times notifications have been retried due to temporary failures. | count | none |
| `notify_throttled_total` | The total number of notifications that have been throttled due to exceeding the rate limit. | count | none |
