#!/bin/bash
# scripts/fix-remotes.sh - Enhanced remote configuration for Patch Guard

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib/common.sh"

main() {
    local mode="${1:-}"
    info "🔧 SmartAlloc Remote Configuration Fix v2.0"

    validate_git_repository

    if [[ "$mode" == "--validate-only" ]]; then
        verify_patch_guard_readiness
        return 0
    fi

    fix_remote_configuration
    setup_branch_tracking
    verify_patch_guard_readiness

    success "Remote configuration completed successfully!"
}

validate_git_repository() {
    if ! git rev-parse --git-dir >/dev/null 2>&1; then
        error "Not a git repository. Please run from project root."
    fi
    info "Git repository validated"
}

fix_remote_configuration() {
    info "Checking remote configuration..."

    if ! git remote get-url origin >/dev/null 2>&1; then
        read -p "Enter repository URL: " REPO_URL
        validate_url "$REPO_URL"
        git remote add origin "$REPO_URL"
        success "Origin remote added: $REPO_URL"
    else
        success "Origin remote already configured"
    fi

    if ! git fetch origin 2>/dev/null; then
        warning "Could not fetch from origin. Checking connectivity..."
        if ! curl -s --head "$REPO_URL" >/dev/null; then
            error "Repository not accessible. Please check URL and permissions."
        fi
    fi
}

setup_branch_tracking() {
    info "Setting up branch tracking..."
    local current_branch="$(git rev-parse --abbrev-ref HEAD)"
    local target_branch="main"

    if git show-ref --verify --quiet refs/remotes/origin/develop; then
        target_branch="develop"
    elif git show-ref --verify --quiet refs/remotes/origin/main; then
        target_branch="main"
    else
        error "No suitable upstream branch found"
    fi

    git branch --set-upstream-to="origin/$target_branch" "$current_branch"
    success "Upstream tracking: $current_branch -> origin/$target_branch"
}

verify_patch_guard_readiness() {
    info "Verifying remote setup..."
    if ! git remote get-url origin >/dev/null 2>&1; then
        error "Origin remote not configured"
    fi

    git rev-parse --abbrev-ref --symbolic-full-name @{u} >/dev/null 2>&1 || \
        warning "No upstream tracking configured"

    git fetch origin --dry-run >/dev/null 2>&1 || \
        error "Cannot fetch from origin"

    success "Remote configuration looks good"
}

main "$@"
