# SmartAlloc v3.3 - Quick Reference

## Quality Gates (Selective)
🔴 **100% STRICT:** PHPCS=0, PHPStan L5=0, p95<2s, Memory≤128MB  
🟡 **Security 35% relaxed:** ≥65% compliance, 0 critical, ≤2 high, ≤5 medium  
🟠 **Maintainability 20% relaxed:** ≥68% coverage, ≤4 warnings, ≤12 complexity  

## Patch Guard
- hotfix: ≤5 files/150 LoC | bugfix: ≤8 files/200 LoC | feature: ≤20 files/600 LoC
- Per commit: ≤8 files, ≤120 LoC

## Pre-Commit
```
composer run quality:selective && ./scripts/patch-guard-check.sh && php baseline-check
```

## WordPress Security (REQUIRED)

Sanitize: sanitize_text_field() | Escape: esc_html() | Caps: current_user_can()

Nonce: wp_verify_nonce() | DB: prepared statements only

## Structure
```nix
includes/Core/ admin/ tests/ scripts/
```

## Commit Format
```elm
type(scope): description
```

See AGENTS.md for complete details.
