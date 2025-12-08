# Claude Code Usage Policy

## Attribution Policy

**Never give Claude attribution in commits, code, or documentation.**

This project is built by humans using AI tools as assistants. All commits, code contributions, and documentation should be attributed to the human developers, not to Claude or any AI tool.

## Guidelines

### ❌ Do NOT Include:
- "Generated with Claude Code" in commit messages
- "Co-Authored-By: Claude Sonnet" in commits
- AI attribution in code comments
- References to Claude in documentation footer/header

### ✅ DO Include:
- Your name and email as the commit author
- Professional commit messages describing WHAT changed
- Standard documentation without AI tool references
- Human authorship for all contributions

## Commit Message Standards

**Good Commit Messages:**
```
Add payment gateway integration with Stripe and PayPal
Update RBAC schema to support multi-agency isolation
Implement webhook handlers for automatic payment confirmation
```

**Bad Commit Messages:**
```
Generated with Claude Code
Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
Add feature (created with AI assistance)
```

## Rationale

- **Professionalism**: Code should reflect human authorship
- **Clarity**: Commit history should describe changes, not tools used
- **Standards**: Follow industry-standard Git practices
- **Ownership**: Maintain clear project ownership and responsibility

## Tool Usage

Claude Code is a development assistant tool, like an IDE or linter. You wouldn't attribute your IDE in commits, and the same applies to AI coding assistants.

Use Claude Code to:
- Generate code snippets and boilerplate
- Review and improve code quality
- Write documentation and specs
- Debug and troubleshoot issues

But always commit and sign work as **your own**.

---

**This policy applies to all commits in the openclient repository.**
- Test development using https://ocdev.premadev.com/