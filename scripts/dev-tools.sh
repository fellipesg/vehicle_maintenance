#!/bin/bash

# Helper script for development tools

case "$1" in
    rector)
        echo "🔧 Running Rector..."
        docker compose exec app vendor/bin/rector "${@:2}"
        ;;
    telescope)
        echo "🔭 Opening Telescope..."
        echo "Access at: http://localhost:8080/telescope"
        ;;
    debugbar)
        echo "🐛 Debugbar is enabled when APP_DEBUG=true"
        echo "Check config at: config/debugbar.php"
        ;;
    xdebug)
        echo "🐛 Xdebug Information:"
        docker compose exec app php -r "xdebug_info();"
        ;;
    logs)
        echo "📋 Showing Laravel logs..."
        docker compose exec app tail -f storage/logs/laravel.log
        ;;
    xdebug-logs)
        echo "📋 Showing Xdebug logs..."
        docker compose exec app tail -f storage/logs/xdebug.log
        ;;
    *)
        echo "Usage: $0 {rector|telescope|debugbar|xdebug|logs|xdebug-logs}"
        echo ""
        echo "Commands:"
        echo "  rector [args]     - Run Rector code refactoring tool"
        echo "  telescope         - Show Telescope URL"
        echo "  debugbar          - Show Debugbar info"
        echo "  xdebug            - Show Xdebug information"
        echo "  logs              - Tail Laravel logs"
        echo "  xdebug-logs       - Tail Xdebug logs"
        exit 1
        ;;
esac

