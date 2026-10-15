#!/usr/bin/env bash
set -uo pipefail

# Khung duyệt tệp đã được GV cấp; điền các TODO về điều kiện, đếm và mã thoát.
if [[ $# -ne 1 ]]; then echo 'Usage: scan-source.sh DIRECTORY' >&2; exit 2; fi
root="$1"

# TODO 1: thiếu/không đọc được thư mục hoặc thiếu php -> exit 2.
if [[ ! -d "$root" || ! -r "$root" ]]; then
  echo "Loi: Thu muc khong ton tai hoac khong the doc." >&2
  exit 2
fi

if ! command -v php >/dev/null 2>&1; then
  echo "Loi: Thieu cong cu php." >&2
  exit 2
fi

list="$(mktemp)" || exit 2
trap 'rm -f -- "$list"' EXIT
if ! find "$root" -type f -name '*.php' -print0 > "$list"; then exit 2; fi

total=0; passed=0; failed=0
while IFS= read -r -d '' file; do
  # TODO 2: tăng total; php -l "$file"; tăng passed hoặc failed.
  # Không dừng vòng lặp khi một tệp bị lỗi.
  total=$((total + 1))
  if php -l "$file" >/dev/null 2>&1; then
    passed=$((passed + 1))
  else
    failed=$((failed + 1))
  fi
done < "$list"

printf 'TOTAL=%s PASS=%s FAIL=%s\n' "$total" "$passed" "$failed"

# TODO 3: rỗng -> 2; có lỗi -> 1; tất cả đạt -> 0.
if [[ "$total" -eq 0 ]]; then
  echo "Loi: Khong tim thay tep PHP nao." >&2
  exit 2
fi

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi

exit 0