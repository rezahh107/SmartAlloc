# Architecture Documentation

## Overview

SmartAlloc follows a layered, event-driven architecture designed for scalability, maintainability, and testability.

## Architecture Layers

### 1. Core Layer

**Purpose**: Foundation components and infrastructure

**Components**:
- `Bootstrap`: Plugin initialization and service wiring
- `Container`: Dependency injection container
- `EventBus`: Event dispatching with deduplication
- `EventKey`: Deduplication key generation
- `Contracts`: Interface definitions

**Responsibilities**:
- Plugin lifecycle management
- Service dependency management
- Event system coordination
- Interface contracts

### 2. Services Layer

**Purpose**: Business logic and domain services

**Components**:
- `AllocationService`: Mentor allocation logic
- `ExportService`: Excel export functionality
- `CrosswalkService`: Data mapping and caching
- `NotificationService`: External notifications
- `HealthService`: System monitoring
- `Metrics`: Performance metrics collection
- `Cache`: Multi-layer caching
- `Db`: Database abstraction
- `Logging`: Structured logging
- `CircuitBreaker`: Failure handling

**Responsibilities**:
- Business rule implementation
- Data processing and transformation
- External service integration
- Performance monitoring
- Error handling

### 3. Integration Layer

**Purpose**: External system adapters

**Components**:
- `GravityForms`: Form processing integration
- `ActionSchedulerAdapter`: Async job processing

**Responsibilities**:
- External API integration
- Data format conversion
- Error handling and retry logic

### 4. HTTP Layer

**Purpose**: Web interface and API

**Components**:
- `RestController`: REST API endpoints
- Admin interfaces (future)

**Responsibilities**:
- HTTP request handling
- Response formatting
- Authentication and authorization
- Input validation

### 5. Infrastructure Layer

**Purpose**: System infrastructure

**Components**:
- `CLI/Commands`: Command-line tools
- Database migrations
- Configuration management

**Responsibilities**:
- System administration
- Deployment support
- Configuration management

## Event System

### Event Flow

```
Form Submission
    ↓
StudentSubmitted Event
    ↓
AutoAssignListener
    ↓
AllocationService.assign()
    ↓
MentorAssigned Event
    ↓
NotifyListener
    ↓
AllocationCommitted Event
    ↓
ExportListener
    ↓
Excel Export
```

### Event Deduplication

Events are deduplicated using a unique key:
`{event_name}:{entry_id}:{version}`

This ensures idempotent processing and prevents duplicate allocations.

## Dependency Injection

### Container Structure

The DI container manages service dependencies:

```php
// Base services
Container::set(Db::class, fn() => new Db());
Container::set(Cache::class, fn() => new Cache());
Container::set(Logging::class, fn() => new Logging());

// Domain services
Container::set(AllocationService::class, fn() => 
    new AllocationService(
        Container::get(Db::class),
        Container::get(CrosswalkService::class),
        Container::get(Logging::class)
    )
);
```

### Service Lifecycle

- **Singleton**: Services are instantiated once and reused
- **Lazy Loading**: Services are created when first requested
- **Dependency Resolution**: Automatic dependency injection

## Caching Strategy

### Three-Layer Cache

1. **L1 Cache (Object Cache)**
   - Redis/Memcached
   - Fastest access
   - TTL: 300 seconds

2. **L2 Cache (Transients)**
   - WordPress transients
   - Medium speed
   - TTL: 600 seconds

3. **L3 Cache (Database)**
   - Precomputed views
   - Slowest access
   - TTL: 3600 seconds

### Cache Invalidation

- Version-based invalidation
- Manual cache clearing
- Automatic expiration

## Database Design

### Core Tables

- `salloc_event_log`: Event tracking
- `salloc_export_log`: Export history
- `salloc_export_errors`: Error details
- `salloc_circuit_breakers`: Failure states
- `salloc_stats_daily`: Daily metrics
- `salloc_metrics`: Performance metrics

### Design Principles

- **Normalization**: Proper database normalization
- **Indexing**: Strategic index placement
- **Constraints**: Data integrity constraints
- **Partitioning**: Large table partitioning (future)

## Security Architecture

### Input Validation

- **Sanitization**: WordPress sanitization functions
- **Validation**: Custom validation rules
- **Normalization**: Data format standardization

### Access Control

- **Capabilities**: WordPress capability system
- **Nonces**: CSRF protection
- **Authorization**: Role-based access control

### Data Protection

- **Masking**: Sensitive data masking in logs
- **Encryption**: Secure data storage (future)
- **Audit**: Comprehensive audit trails

## Performance Considerations

### Optimization Strategies

1. **Caching**: Multi-layer caching system
2. **Async Processing**: Background job processing
3. **Database Optimization**: Query optimization
4. **Resource Management**: Memory and CPU optimization

### Monitoring

- **Metrics Collection**: Performance metrics
- **Health Checks**: System health monitoring
- **Error Tracking**: Comprehensive error logging

## Scalability

### Horizontal Scaling

- **Stateless Services**: Stateless service design
- **Database Sharding**: Future database sharding
- **Load Balancing**: Multiple server support

### Vertical Scaling

- **Resource Optimization**: Efficient resource usage
- **Caching**: Reduced database load
- **Async Processing**: Non-blocking operations

## Testing Strategy

### Test Types

1. **Unit Tests**: Individual component testing
2. **Integration Tests**: Service interaction testing
3. **End-to-End Tests**: Complete workflow testing
4. **Performance Tests**: Load and stress testing

### Test Coverage

- **Code Coverage**: Minimum 80% coverage
- **Critical Paths**: All critical paths tested
- **Edge Cases**: Error condition testing

## Deployment

### Environment Configuration

- **Development**: Local development setup
- **Staging**: Pre-production testing
- **Production**: Live environment

### Deployment Process

1. **Code Review**: Pull request review
2. **Testing**: Automated testing
3. **Staging**: Staging environment deployment
4. **Production**: Production deployment
5. **Monitoring**: Post-deployment monitoring

## Future Considerations

### Planned Enhancements

- **Microservices**: Service decomposition
- **API Gateway**: Centralized API management
- **Message Queues**: Advanced async processing
- **Machine Learning**: Intelligent allocation algorithms

### Technology Evolution

- **PHP 8.2+**: Latest PHP features
- **WordPress 6.4+**: Latest WordPress features
- **Modern Frontend**: React/Vue.js admin interface 