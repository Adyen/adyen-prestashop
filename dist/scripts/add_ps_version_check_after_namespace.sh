#!/bin/bash

path="$1"
code_to_add="$2"

find "$path" -type f -name "*.php" | while read -r file; do
    # Add PS version check after namespace line
    awk -v code="$code_to_add" '
    {
        if ($0 ~ /^namespace /) {
            print $0 "\n\n" code;
        } else {
            print $0;
        }
    }' "$file" > temp && mv temp "$file"
done