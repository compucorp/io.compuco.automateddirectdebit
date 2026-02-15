# Code Review

Review the current changes for issues before committing.

## Instructions

1. Get the current diff:
   ```bash
   git diff
   git diff --staged
   ```

2. Review against the checklist in `.ai/ai-code-review.md`

3. Check for:
   - Security vulnerabilities (SQL injection, XSS)
   - Missing tests
   - Code style violations
   - Performance issues
   - CiviCRM best practices

4. Output findings using severity levels:
   - **BLOCKER**: Must fix before merge
   - **WARNING**: Should fix
   - **SUGGESTION**: Optional improvement
   - **QUESTION**: Needs clarification

## Output Format

```markdown
## Summary
Brief assessment of the changes.

## Issues Found

### BLOCKER
- [File:Line] Description

### WARNING
- [File:Line] Description

### SUGGESTION
- Improvement suggestions

## Positive Notes
- What was done well
```

If no issues found, confirm the code looks good to commit.