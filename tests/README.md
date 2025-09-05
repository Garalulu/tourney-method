# Test Architecture Documentation

## Overview

This testing architecture provides comprehensive coverage for the Tourney Method application with a focus on security, reliability, and maintainability. The architecture follows a three-tier approach: Unit → Integration → Functional testing.

## Test Structure

```
tests/
├── unit/                   # Unit tests - isolated component testing
│   ├── Services/          # Business logic tests
│   ├── Models/            # Data model tests
│   ├── Utils/             # Utility function tests
│   └── ConfigTest.php     # Configuration tests
├── integration/           # Integration tests - component interaction
│   ├── DatabaseTest.php   # Database integration
│   └── OAuthFlowTest.php  # OAuth flow integration
├── functional/            # Functional tests - end-to-end user journeys
│   └── AdminLoginJourneyTest.php  # Complete login flows
├── helpers/               # Test utilities and helpers
│   ├── TestHelper.php     # Common test utilities
│   └── OsuApiMock.php     # API response mocking
├── fixtures/              # Test data and fixtures
├── bootstrap.php          # Test environment setup
├── run-tests.php          # Test runner script
└── README.md             # This documentation
```

## Test Types

### 1. Unit Tests (67+ tests)
**Purpose**: Test individual components in isolation
**Coverage**: Models, Services, Utilities, Configuration
**Focus**: Logic validation, edge cases, error handling

**Key Test Files**:
- `AuthServiceTest.php` - OAuth service logic
- `AdminUserTest.php` - User model and session management  
- `SecurityHelperTest.php` - Security utilities and validation

**Run Command**: `php tests/run-tests.php run unit`

### 2. Integration Tests (25+ tests)
**Purpose**: Test component interactions and data flows
**Coverage**: OAuth flow, database operations, session management
**Focus**: Component integration, API interactions, security flows

**Key Test Files**:
- `OAuthFlowTest.php` - Complete OAuth 2.0 integration
- `DatabaseTest.php` - Database schema and operations

**Run Command**: `php tests/run-tests.php run integration`

### 3. Functional Tests (15+ tests)
**Purpose**: Test complete user journeys and workflows
**Coverage**: End-to-end login flows, user experience paths
**Focus**: Real user scenarios, browser-like behavior, Korean localization

**Key Test Files**:
- `AdminLoginJourneyTest.php` - Complete admin authentication journeys

**Run Command**: `php tests/run-tests.php run functional`

## Test Groups

Tests are organized into logical groups for targeted testing:

### Security Tests (`@group security`)
- OAuth CSRF protection
- Session security validation
- Input validation and sanitization
- Admin authorization enforcement

**Run Command**: `php tests/run-tests.php group security`

### OAuth Tests (`@group oauth`)
- OAuth 2.0 flow validation
- Token exchange and validation
- State parameter handling

**Run Command**: `php tests/run-tests.php group oauth`

### Localization Tests (`@group localization`)
- Korean language support
- Error message localization
- Timezone handling (Asia/Seoul)

**Run Command**: `php tests/run-tests.php group localization`

## Test Helpers and Utilities

### TestHelper Class
Provides common testing utilities:
- Database setup and cleanup
- Session management
- Test user creation
- Environment configuration
- Assertion helpers

### OsuApiMock Class
Mock osu! API responses:
- OAuth token responses
- User information data
- Error scenarios and edge cases
- Rate limiting simulation

## Running Tests

### Basic Usage
```bash
# Run all tests
php tests/run-tests.php run all

# Run specific test suite
php tests/run-tests.php run unit
php tests/run-tests.php run integration
php tests/run-tests.php run functional

# Run with coverage
php tests/run-tests.php coverage all

# Run specific group
php tests/run-tests.php group security
```

### PHPUnit Direct Usage
```bash
# Run all tests
./vendor/bin/phpunit

# Run specific suite
./vendor/bin/phpunit --testsuite unit
./vendor/bin/phpunit --testsuite integration

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/

# Run specific test file
./vendor/bin/phpunit tests/unit/Services/AuthServiceTest.php
```

## Test Data Management

### Environment Variables
Tests use isolated environment variables:
- `OSU_CLIENT_ID=test_client_id`
- `OSU_CLIENT_SECRET=test_client_secret` 
- `APP_URL=http://localhost:8000`
- `PHPUNIT_RUNNING=true`

### Database Testing
- Isolated SQLite database for each test run
- Fresh schema loaded from `data/database/schema.sql`
- Automatic cleanup after test completion

### Session Management
- Clean session state for each test
- Test-specific session data creation
- Automatic session cleanup

## Quality Assurance Features

### Security Testing
- **CSRF Protection**: OAuth state parameter validation
- **Session Security**: Timeout handling, secure configuration
- **Input Validation**: Parameter sanitization and limits
- **Authorization**: Admin access control testing

### Error Handling
- **OAuth Errors**: Invalid codes, expired tokens, API failures
- **Network Errors**: Timeouts, DNS failures, rate limiting
- **Validation Errors**: Malformed input, missing parameters
- **Korean Error Messages**: Localized error display

### Performance Testing
- **Response Time Validation**: OAuth flow performance
- **Session Efficiency**: Memory usage and cleanup
- **Database Query Optimization**: Index usage verification

## Test Coverage Goals

- **Unit Tests**: >95% code coverage for core business logic
- **Integration Tests**: All critical user flows validated
- **Security Tests**: 100% coverage of security requirements
- **Functional Tests**: All user journeys from login to admin access

## Best Practices

### Writing Tests
1. **One Assertion Per Test**: Focus on single behavior validation
2. **Descriptive Names**: Use `it_does_something_when_condition()` format
3. **Arrange-Act-Assert**: Clear test structure
4. **Mock External Dependencies**: Use OsuApiMock for API calls
5. **Clean State**: Use TestHelper for consistent test setup

### Test Maintenance
1. **Regular Execution**: Run full test suite on every commit
2. **Coverage Monitoring**: Maintain high coverage percentage
3. **Security Focus**: Prioritize security test scenarios
4. **Documentation**: Keep test documentation updated

### Debugging Tests
1. **Verbose Output**: Use `--verbose` flag for detailed information
2. **Isolated Execution**: Run single test files during debugging
3. **Group Testing**: Use groups to focus on specific functionality
4. **Coverage Analysis**: Use coverage reports to identify gaps

## Integration with CI/CD

The test architecture supports automated execution in CI/CD pipelines:

```bash
# CI/CD test execution
php tests/run-tests.php run all
php tests/run-tests.php coverage all
php tests/run-tests.php group security
```

## Troubleshooting

### Common Issues
1. **Session Conflicts**: Ensure clean session state between tests
2. **Database Locks**: Windows may require delays for file cleanup
3. **Environment Variables**: Verify test environment configuration
4. **Path Issues**: Use absolute paths in test configuration

### Debug Commands
```bash
# Setup test environment only
php tests/run-tests.php setup

# Clean test environment
php tests/run-tests.php clean

# Run specific test with verbose output
./vendor/bin/phpunit --verbose tests/integration/OAuthFlowTest.php
```

This test architecture ensures comprehensive validation of the OAuth authentication system while maintaining security best practices and Korean localization support.