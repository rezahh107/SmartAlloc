# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.1.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within SmartAlloc, please send an email to security@smartalloc.com. All security vulnerabilities will be promptly addressed.

## Security Features

### Input Validation
- All user inputs are validated and sanitized
- Gravity Forms data is normalized and validated
- SQL injection prevention through prepared statements

### Access Control
- Capability-based access control (`manage_smartalloc`)
- REST API endpoints require proper authentication
- Admin functions check user capabilities

### Data Protection
- Sensitive data masking in logs
- Secure file handling for exports
- Database queries use WordPress security functions

### Error Handling
- No sensitive information in error messages
- Graceful degradation on failures
- Circuit breaker pattern for external services

## Best Practices

1. Keep the plugin updated to the latest version
2. Use strong passwords for WordPress admin
3. Enable HTTPS on your site
4. Regularly backup your database
5. Monitor error logs for suspicious activity

## Security Checklist

- [ ] All SQL queries use prepared statements
- [ ] Input validation on all user inputs
- [ ] Output escaping for all displayed data
- [ ] Capability checks on admin functions
- [ ] Nonce verification on forms
- [ ] Secure file upload handling
- [ ] Error logging without sensitive data
- [ ] HTTPS enforcement for admin areas 