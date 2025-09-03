# Notification Service Configuration

| Constant Name | Default Value | Description | Tuning Guidance |
| --- | --- | --- | --- |
| `SMARTALLOC_NOTIFY_MAX_TRIES` | `5` | Maximum number of notification retry attempts before message is pushed to the DLQ. | Increase for transient downstream issues; lower for faster failure. |
| `SMARTALLOC_NOTIFY_BASE_DELAY` | `5` | Base delay in seconds used for exponential backoff between mail retries. | Adjust to control initial retry wait; lower for quicker retries. |
| `SMARTALLOC_NOTIFY_BACKOFF_CAP` | `600` | Maximum delay in seconds for backoff between retries. | Raise to allow longer wait during prolonged outages. |
