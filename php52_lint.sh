#!/usr/bin/env bash
echo "Checking PHP 5.2 syntax..."
for filename in $(find ./ -maxdepth 100 -name "*.php"); do
    RESULT=$(php52 -l "$filename")
    if ! $(echo "$RESULT" | grep -q 'No syntax errors detected in'); then
        echo "Syntax error found in file: $filename"
        echo "$RESULT"
    fi
done

echo "Done!"
