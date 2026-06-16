#!/usr/bin/env python3
import os
import shutil
import subprocess
import sys
from pypinyin import lazy_pinyin, Style


TEMPLATE_USER = "qygjj_agreement.html"
TEMPLATE_POLICY = "qygjj_privacy.html"

OLD_COMPANY = "万路征途（北京）科技有限公司"
OLD_APP_NAME = "清砚工具集"


def run_cmd(cmd):
    print(f"执行命令: {cmd}")
    result = subprocess.run(cmd, shell=True)
    if result.returncode != 0:
        print("命令执行失败")
        sys.exit(1)


def get_initials(text):
    """获取中文首字母"""
    return "".join(lazy_pinyin(text, style=Style.FIRST_LETTER))


def get_full_pinyin(text):
    """获取中文全拼"""
    return "".join(lazy_pinyin(text))


def generate_filename(app_name):
    """优先使用首字母，如果存在则使用全拼"""
    initials = get_initials(app_name)
    full_pinyin = get_full_pinyin(app_name)

    user_file = f"{initials}_agreement.html"
    policy_file = f"{initials}_privacy.html"

    if os.path.exists(user_file) or os.path.exists(policy_file):
        print(f"⚠ 文件已存在，改用全拼命名: {full_pinyin}")
        user_file = f"{full_pinyin}_agreement.html"
        policy_file = f"{full_pinyin}_privacy.html"

    return user_file, policy_file


def process_app(company_name, app_name):
    print(f"\n处理应用: {app_name}")

    user_target, policy_target = generate_filename(app_name)

    print(f"生成文件: {user_target}, {policy_target}")

    shutil.copyfile(TEMPLATE_USER, user_target)
    shutil.copyfile(TEMPLATE_POLICY, policy_target)

    for file in [user_target, policy_target]:
        with open(file, "r", encoding="utf-8") as f:
            content = f.read()

        content = content.replace(OLD_COMPANY, company_name)
        content = content.replace(OLD_APP_NAME, app_name)

        with open(file, "w", encoding="utf-8") as f:
            f.write(content)

    run_cmd(f"git add {user_target} {policy_target}")


def main():
    if len(sys.argv) < 3:
        print("用法：")
        print("python3 generate_html.py 公司名 appA appB appC")
        sys.exit(1)

    company_name = sys.argv[1]
    apps = sys.argv[2:]  # 支持多个app，用空格分隔

    if not os.path.exists(TEMPLATE_USER) or not os.path.exists(TEMPLATE_POLICY):
        print("模板文件不存在，请确认当前目录有模板文件")
        sys.exit(1)

    for app in apps:
        process_app(company_name, app)

    print("\n✅ 所有文件生成完成并已 git add")


if __name__ == "__main__":
    main()