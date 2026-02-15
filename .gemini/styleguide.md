# Gemini Code Review Style Guide

This guide defines how Gemini should review code for this CiviCRM extension.

## Review Scope

When reviewing pull requests, evaluate against:

1. **Security** - SQL injection, XSS, sensitive data exposure
2. **Performance** - N+1 queries, unnecessary database calls
3. **Code Quality** - Style, DRY, readability
4. **Testing** - Coverage, test quality
5. **CiviCRM Patterns** - API4 usage, hooks, conventions

## Shared Documentation

Read and apply standards from:

- `.ai/shared-development-guide.md` - Git discipline, code quality
- `.ai/ai-code-review.md` - Review checklist and process
- `.ai/civicrm.md` - CiviCRM-specific patterns
- `.ai/extension.md` - Extension structure
- `.ai/unit-testing-guide.md` - Testing requirements

## Severity Levels

### BLOCKER

Must be fixed before merge:

- Security vulnerabilities
- Bugs that will cause failures
- Breaking changes without migration path
- Missing required tests for features/bug fixes

### WARNING

Should be addressed:

- Performance issues
- Deviations from best practices
- Missing edge case handling
- Incomplete error handling

### SUGGESTION

Optional improvements:

- Style preferences
- Minor refactoring opportunities
- Documentation improvements

### QUESTION

Request for clarification:

- Unclear intent
- Unusual patterns that may be intentional
- Business logic questions

## Review Output Format

```markdown
## Summary
Brief overall assessment.

## Security
- [BLOCKER/WARNING] Issue description

## Performance
- [WARNING] Issue description

## Code Quality
- [SUGGESTION] Improvement idea

## Testing
- [BLOCKER] Missing test for new feature

## Questions
- [QUESTION] Why is X implemented this way?
```

## CiviCRM-Specific Checks

### API Usage
- Prefer API4 over API3
- Use `is_array()` guards on API4 results
- Check for proper error handling

### Custom Data
- Access via API4 with `custom.*` select
- Don't hardcode custom field IDs

### Hooks
- Implement hooks correctly
- Document custom hooks

### Extension Patterns
- Follow PSR-0/PSR-4 conventions
- Use proper upgrade path for schema changes
- Test extension lifecycle methods

## File-Specific Guidelines

### PHP Files
- Check for proper namespacing
- Verify PHPStan compatibility
- Look for SQL injection risks

### Test Files
- Verify Arrange-Act-Assert pattern
- Check for positive/negative/edge cases
- Ensure test isolation

### Template Files
- Check for XSS vulnerabilities
- Verify proper escaping

## Do Not Flag

- Civix-generated files (`*.civix.php`)
- Vendor dependencies
- Test fixtures/mock data
