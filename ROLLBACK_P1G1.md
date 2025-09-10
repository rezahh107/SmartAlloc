# ROLLBACK — P1.G1-PHPCS-008

1. git checkout -- src/Infra/GF/HookBootstrap.php src/Infra/GF/IdempotencyGuard.php
2. git rm PHPCS_FIXES.json ROLLBACK_P1G1.md
3. git commit -m "chore: rollback P1.G1-PHPCS-008"
