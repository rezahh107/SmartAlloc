# Upgrade Guide

## Upgrading SmartAlloc

### Before Upgrading

1. **Backup your site**
   - Database backup
   - Plugin files backup
   - Upload directory backup

2. **Test in staging environment**
   - Always test upgrades in a staging environment first
   - Verify all functionality works as expected

3. **Check compatibility**
   - Ensure WordPress version compatibility
   - Verify PHP version requirements
   - Check Gravity Forms compatibility

### Upgrade Process

#### Automatic Upgrade (Recommended)

1. Go to WordPress Admin → Plugins
2. Find SmartAlloc and click "Update Now"
3. Activate the plugin if needed
4. Run database migrations: `wp smartalloc upgrade`

#### Manual Upgrade

1. Deactivate the current plugin
2. Replace plugin files with new version
3. Activate the plugin
4. Run database migrations: `wp smartalloc upgrade`

### Database Migrations

The plugin automatically runs migrations on activation, but you can also run them manually:

```bash
wp smartalloc upgrade
```

### Post-Upgrade Tasks

1. **Verify configuration**
   - Check Gravity Forms integration
   - Verify export configuration
   - Test REST API endpoints

2. **Check logs**
   - Review error logs for any issues
   - Verify event processing

3. **Test functionality**
   - Submit a test form
   - Verify allocation process
   - Test export functionality

### Troubleshooting Upgrades

#### Common Issues

1. **Database errors**
   - Check database permissions
   - Verify table structure
   - Run migrations manually

2. **Plugin not activating**
   - Check PHP version compatibility
   - Verify WordPress version
   - Check for conflicting plugins

3. **Form processing issues**
   - Verify Gravity Forms integration
   - Check field mapping
   - Review error logs

#### Rollback Procedure

If issues occur after upgrade:

1. Deactivate the plugin
2. Restore previous version from backup
3. Restore database if needed
4. Contact support if issues persist

### Version-Specific Notes

#### Upgrading to 1.1.0

- New event-driven architecture
- Config-driven export system
- Enhanced REST API
- Improved error handling
- Better logging and monitoring

### Support

If you encounter issues during upgrade:

1. Check the troubleshooting section
2. Review error logs
3. Contact support with detailed information
4. Provide system information and error details 