#!/bin/bash

# Script to run Rector for code refactoring
# Usage: ./scripts/rector.sh process [path] [options]

cd "$(dirname "$0")/../.." || exit

echo "🔧 Running Rector..."

if [ -z "$1" ]; then
    docker compose exec app vendor/bin/rector process --dry-run
else
    docker compose exec app vendor/bin/rector "$@"
fi

