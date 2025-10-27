#!/bin/bash
#
# View PXE boot logs
#

LOG_FILE="boot.log"

if [ ! -f "$LOG_FILE" ]; then
    echo "No log file found at $LOG_FILE"
    echo "The log file will be created automatically when boot attempts occur."
    exit 1
fi

echo "=== PXE Boot Logs ==="
echo

case "${1:-tail}" in
    "tail")
        tail -f "$LOG_FILE"
        ;;
    "all")
        cat "$LOG_FILE"
        ;;
    "success")
        grep "Status: SUCCESS" "$LOG_FILE"
        ;;
    "errors")
        grep -E "Status: (NOT_REGISTERED|INVALID_MAC|NO_MAC|ERROR|NO_KICKSTART)" "$LOG_FILE"
        ;;
    "today")
        grep "$(date +%Y-%m-%d)" "$LOG_FILE"
        ;;
    *)
        echo "Usage: $0 [tail|all|success|errors|today]"
        echo
        echo "Options:"
        echo "  tail     - Follow log file (default)"
        echo "  all      - Show all logs"
        echo "  success  - Show successful boots only"
        echo "  errors   - Show errors only"
        echo "  today    - Show today's logs"
        exit 1
        ;;
esac
