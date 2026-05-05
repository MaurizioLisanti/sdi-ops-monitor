# AI-Assisted Development Workflow

This folder contains the agentic development pipeline used to build this project.

## What it is

A structured prompt pipeline for AI-assisted software development, compatible with:
- **Claude Code** (primary)
- **Qwen 3 Coder**
- **Goose**
- Any AI coding assistant that reads files

## Pipeline structure

| Prompt | Role |
|--------|------|
| PROMPT_01 | Discovery Interview |
| PROMPT_02 | Repo Seed Generator |
| PROMPT_03 | Planner Agent |
| PROMPT_04 | Executor Agent |
| PROMPT_05 | Reviewer Agent |
| PROMPT_06 | Complexity Manager |
| PROMPT_07 | Integration Guard |

## Philosophy

AI handles boilerplate and repetitive tasks.
The developer owns architecture decisions, code review, and quality gates.
Every task requires: PHPUnit PASS + PHPCS PASS + human review before merge.
