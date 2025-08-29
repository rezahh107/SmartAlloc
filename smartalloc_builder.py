#!/usr/bin/env python3
"""SmartAlloc project prompt builder supporting local and GitHub modes."""

import argparse
import base64
import json
import os
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict

import requests

PROMPT_TEMPLATE = """# 📋 گزارش وضعیت پروژه SmartAlloc

## 🎯 نقش شما: مدیر پروژه هوشمند
شما **مدیر پروژه AI** هستید که باید بر اساس فایل‌های ارائه شده:
1. وضعیت کلی پروژه را تحلیل کنید
2. نقاط قوت و ضعف را شناسایی کنید  
3. اقدامات بعدی را پیشنهاد دهید
4. ریسک‌ها و موانع را اعلام کنید

## 📊 داده‌های ورودی
در ادامه **سه فایل اصلی** پروژه را دریافت می‌کنید:
- ai_context.json: context هوش مصنوعی و تاریخچه تصمیمات
- FEATURES.md: لیست ویژگی‌ها و وضعیت پیشرفت
- PROJECT_STATE.md: وضعیت فعلی پروژه و KPIs

## 🎯 خروجی مورد انتظار
لطفاً پاسخ خود را در این قالب ارائه دهید:

### 📈 خلاصه وضعیت
- **درصد تکمیل کلی**: X%
- **ویژگی‌های تکمیل شده**: X از Y
- **وضعیت کیفیت**: عالی/خوب/متوسط/ضعیف
- **ریسک اصلی**: [نام ریسک]

### ✅ نقاط قوت
1. [نقطه قوت ۱]
2. [نقطه قوت ۲]

### ❌ نقاط ضعف و مشکلات
1. [مشکل ۱ + راه حل پیشنهادی]
2. [مشکل ۲ + راه حل پیشنهادی]

### 🚀 اقدامات بعدی (اولویت‌بندی شده)
1. **فوری**: [اقدام فوری]
2. **مهم**: [اقدام مهم]  
3. **مطلوب**: [اقدام مطلوب]

### ⚠️ ریسک‌ها و هشدارها
- [ریسک ۱]: احتمال X% - تأثیر: بالا/متوسط/پایین
- [ریسک ۲]: احتمال Y% - تأثیر: بالا/متوسط/پایین

### 📅 پیش‌بینی زمانبندی
- **تخمین اتمام فاز فعلی**: X روز
- **تخمین اتمام کل پروژه**: Y روز
- **احتمال تأخیر**: Z%

---

## 📂 فایل‌های پروژه

"""

FILES = ["ai_context.json", "FEATURES.md", "PROJECT_STATE.md"]


def read_local_files() -> Dict[str, str]:
    """Read required files from current directory."""
    contents: Dict[str, str] = {}
    for name in FILES:
        path = Path(name)
        if not path.exists():
            raise FileNotFoundError(f"Missing local file: {name}")
        try:
            contents[name] = path.read_text(encoding="utf-8").strip()
        except OSError as exc:
            raise RuntimeError(f"Error reading {name}: {exc}") from exc
    return contents


def fetch_github_files(repo: str, branch: str) -> Dict[str, str]:
    """Fetch files from GitHub using REST API."""
    if "/" not in repo:
        raise ValueError("--repo must be in 'owner/repo' format")
    owner, repo_name = repo.split("/", 1)
    token = os.getenv("GITHUB_TOKEN")
    headers = {"Accept": "application/vnd.github+json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    base_url = f"https://api.github.com/repos/{owner}/{repo_name}/contents"
    contents: Dict[str, str] = {}
    for name in FILES:
        url = f"{base_url}/{name}?ref={branch}"
        try:
            resp = requests.get(url, headers=headers, timeout=30)
        except requests.RequestException as exc:
            raise RuntimeError(f"Network error fetching {name}: {exc}") from exc
        if resp.status_code != 200:
            raise RuntimeError(f"GitHub fetch failed for {name}: {resp.status_code} {resp.text}")
        data = resp.json()
        try:
            contents[name] = base64.b64decode(data.get("content", "")).decode("utf-8").strip()
        except (KeyError, ValueError) as exc:
            raise RuntimeError(f"Invalid content for {name}: {exc}") from exc
    return contents


def build_prompt(files: Dict[str, str]) -> str:
    """Merge files into final prompt."""
    timestamp = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S UTC")
    prompt = PROMPT_TEMPLATE
    prompt += f"### 📄 ai_context.json\n```json\n{files['ai_context.json']}\n```\n\n"
    prompt += f"### 📄 FEATURES.md\n```markdown\n{files['FEATURES.md']}\n```\n\n"
    prompt += f"### 📄 PROJECT_STATE.md\n```markdown\n{files['PROJECT_STATE.md']}\n```\n\n"
    prompt += f"---\nآخرین بروزرسانی: {timestamp}\n"
    return prompt


def write_outputs(files: Dict[str, str], prompt: str) -> None:
    """Write prompt and JSON status files."""
    prompt_path = Path("project_manager_prompt.md")
    prompt_path.write_text(prompt, encoding="utf-8")
    status = {
        "last_update_utc": datetime.utcnow().replace(tzinfo=timezone.utc).isoformat(),
        "files": files,
        "prompt_file": str(prompt_path),
    }
    with open("current_project_status.json", "w", encoding="utf-8") as fh:
        json.dump(status, fh, ensure_ascii=False, indent=2)


def main() -> int:
    parser = argparse.ArgumentParser(description="Build SmartAlloc project prompt")
    parser.add_argument("--repo", help="GitHub repository as owner/repo")
    parser.add_argument("--branch", default="main", help="Branch name when using GitHub mode")
    args = parser.parse_args()

    try:
        if args.repo:
            print("[GITHUB MODE]")
            files = fetch_github_files(args.repo, args.branch)
        else:
            print("[LOCAL MODE]")
            files = read_local_files()
        prompt = build_prompt(files)
        write_outputs(files, prompt)
        print("Wrote project_manager_prompt.md and current_project_status.json")
    except Exception as exc:  # pylint: disable=broad-except
        print(f"Error: {exc}")
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
