# Pre-Commit Checks

Run all pre-commit validations before committing.

## Instructions

Execute these checks and report results:

### 1. Run Linter

```bash
./bin/phpcs.phar --standard=phpcs-ruleset.xml $(git diff --staged --name-only --diff-filter=d | grep '\.php$' | tr '\n' ' ')
```

If linting fails, show errors and suggest running auto-fix:
```bash
./bin/phpcbf.phar --standard=phpcs-ruleset.xml <files>
```

### 2. Run Tests

```bash
phpunit5
```

Report any test failures.

### 3. Check for Debug Code

Search staged files for:
- `var_dump(`
- `print_r(`
- `console.log(`
- `die(`
- `exit(`
- `dd(`

### 4. Check for Secrets

Search staged files for potential secrets:
- API keys
- Passwords
- Tokens
- Hardcoded credentials

### 5. Validate Commit Message Format

If a commit message is provided, verify it follows:
```
JIRA-TICKET: Brief description
```

## Output

Report pass/fail for each check:

```
Pre-Commit Checks
-----------------
[ ] Linting: PASS/FAIL
[ ] Tests: PASS/FAIL
[ ] No debug code: PASS/FAIL
[ ] No secrets: PASS/FAIL
[ ] Commit message format: PASS/FAIL (if applicable)

Overall: READY TO COMMIT / ISSUES FOUND
```

If any checks fail, provide specific details on what needs to be fixed.