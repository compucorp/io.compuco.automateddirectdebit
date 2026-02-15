# AI Code Review Guide

This guide defines the code review process for AI-assisted development.

## Pre-Push Review Workflow

Before pushing code, request an AI review:

1. Generate diff of changes:
   ```bash
   git diff --staged > /tmp/changes.diff
   # Or for all uncommitted changes:
   git diff > /tmp/changes.diff
   ```

2. Request review from any AI tool (Claude, Gemini, Copilot)

3. Address any BLOCKER or WARNING issues before pushing

## GitHub PR Review

When reviewing a PR:

1. Read the PR description and linked ticket
2. Review the full diff
3. Check against the review checklist below
4. Provide feedback using severity levels

## Review Checklist

### Security

- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities
- [ ] No sensitive data exposure
- [ ] No hardcoded credentials
- [ ] Input validation present where needed
- [ ] Payment data handled according to PCI guidelines

### Performance

- [ ] No N+1 query patterns
- [ ] Appropriate use of caching
- [ ] No unnecessary database calls in loops
- [ ] Batch operations used where appropriate

### Code Quality

- [ ] Code follows project style guidelines
- [ ] No code duplication (DRY principle)
- [ ] Functions are focused and reasonably sized
- [ ] Variable/function names are meaningful
- [ ] No commented-out code
- [ ] No debug statements (var_dump, print_r, etc.)

### Testing

- [ ] New functionality has tests
- [ ] Bug fixes include regression tests
- [ ] Tests cover positive, negative, and edge cases
- [ ] Tests are isolated and don't depend on external state
- [ ] Mock external services appropriately

### CiviCRM Specific

- [ ] API4 used instead of API3 where possible
- [ ] Hooks implemented correctly
- [ ] Custom data accessed through proper APIs
- [ ] Extension upgrade path considered
- [ ] Compatible with target CiviCRM version (5.51.3)

### Documentation

- [ ] Complex logic is commented
- [ ] Public APIs are documented
- [ ] README updated if needed
- [ ] CHANGELOG updated for significant changes

## Severity Levels

Use these prefixes when providing feedback:

### BLOCKER

Must be fixed before merge. Security vulnerabilities, bugs, or code that will break functionality.

```
BLOCKER: SQL injection vulnerability - user input directly concatenated into query
```

### WARNING

Should be fixed but not blocking. Code smells, potential issues, or deviations from best practices.

```
WARNING: This loop queries the database on each iteration - consider batch loading
```

### SUGGESTION

Optional improvements. Style preferences, minor optimizations, or alternative approaches.

```
SUGGESTION: Consider extracting this logic into a separate method for reusability
```

### QUESTION

Clarification needed. When the reviewer doesn't understand the intent or needs context.

```
QUESTION: What's the expected behavior when mandateId is null here?
```

## Cross-Tool Review Workflow

Any AI tool can review code written by any other AI (or human):

1. Claude can review Copilot-generated code
2. Gemini can review Claude-generated code
3. Human reviewers can use AI assistants for review

The goal is catching issues regardless of who wrote the code.

## Responding to Review Feedback

When receiving AI review feedback:

1. **Evaluate critically** - AI suggestions aren't always correct
2. **Fix valid issues** - Address legitimate problems
3. **Push back when appropriate** - Explain why if you disagree
4. **Ask for clarification** - If feedback is unclear

## Review Output Format

When providing a review, structure it as:

```markdown
## Summary
Brief overall assessment of the changes.

## Issues Found

### BLOCKER
- Issue description and location
- Suggested fix

### WARNING
- Issue description and location
- Suggested fix

### SUGGESTION
- Improvement suggestion

## Positive Notes
- What was done well
```
