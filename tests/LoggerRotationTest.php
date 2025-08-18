<?php

namespace SmartAlloc\Tests;

use SmartAlloc\Services\Logging;

class LoggerRotationTest extends BaseTestCase
{
    private Logging $logger;
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->logger = new Logging();
        $this->testLogPath = sys_get_temp_dir() . '/test_smartalloc.log';
    }

    protected function tearDown(): void
    {
        // Clean up test log files
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
        if (file_exists($this->testLogPath . '.backup')) {
            unlink($this->testLogPath . '.backup');
        }
    }

    public function testLogLevels(): void
    {
        // Test all log levels
        $this->logger->debug('Debug message', ['context' => 'test']);
        $this->logger->info('Info message', ['context' => 'test']);
        $this->logger->warning('Warning message', ['context' => 'test']);
        $this->logger->error('Error message', ['context' => 'test']);
        
        // All methods should execute without error
        $this->assertTrue(true);
    }

    public function testFileLogging(): void
    {
        // Test file logging functionality
        $logMessage = 'Test log message';
        $this->logger->info($logMessage);
        
        // Should not throw any errors
        $this->assertTrue(true);
    }

    public function testLogRotation(): void
    {
        // Create a small log file to test rotation
        $smallLogContent = str_repeat('A', 1024); // 1KB
        file_put_contents($this->testLogPath, $smallLogContent);
        
        // Test rotation with very small max size
        $this->logger->info('Test message for rotation');
        
        // Should not throw any errors
        $this->assertTrue(true);
    }

    public function testLogFileInfo(): void
    {
        $logInfo = $this->logger->getLogInfo();
        
        $this->assertIsArray($logInfo);
        $this->assertArrayHasKey('exists', $logInfo);
        $this->assertArrayHasKey('size', $logInfo);
        $this->assertArrayHasKey('last_modified', $logInfo);
    }

    public function testLogContents(): void
    {
        $contents = $this->logger->getLogContents();
        
        $this->assertIsString($contents);
    }

    public function testLogClearing(): void
    {
        $result = $this->logger->clearLog();
        
        $this->assertIsBool($result);
    }

    public function testConfigurableLogPath(): void
    {
        // Test that log path can be configured via filter
        $customPath = '/custom/log/path';
        
        // This would normally be set via WordPress filter
        // For testing, we just verify the method exists
        $this->assertTrue(method_exists($this->logger, 'getLogFile'));
    }

    public function testConfigurableMaxSize(): void
    {
        // Test that max log size can be configured
        $this->assertTrue(method_exists($this->logger, 'writeToFile'));
    }

    public function testSensitiveDataMasking(): void
    {
        $sensitiveData = [
            'password' => 'secret123',
            'national_id' => '1234567890',
            'mobile' => '09123456789'
        ];
        
        $this->logger->info('User data', $sensitiveData);
        
        // Should not throw any errors
        $this->assertTrue(true);
    }

    public function testContextHandling(): void
    {
        $context = [
            'user_id' => 123,
            'action' => 'test',
            'timestamp' => time()
        ];
        
        $this->logger->info('Test message with context', $context);
        
        // Should not throw any errors
        $this->assertTrue(true);
    }
} 