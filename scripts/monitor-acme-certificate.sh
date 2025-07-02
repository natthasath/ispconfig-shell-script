#!/bin/bash

# ==== SSL Certificate Monitoring Script ====
# Purpose: Monitor ACME.sh SSL certificate renewal and send LINE notification on failure
# Schedule: Daily at midnight via cron
# Author: IDT NIDA

# ==== Configuration ====
ACCESS_TOKEN="YOUR_CHANNEL_ACCESS_TOKEN"   # Replace with your Channel Access Token
TO="USER_OR_GROUP_ID"                      # Replace with the recipient's LINE userId or groupId

# Server and domain configuration
SERVER_NAME="$(hostname)"
DOMAINS=("faytest.nida.ac.th")
ACME_LOG="/root/.acme.sh/acme.sh.log"
LOG_FILE="/var/log/ssl-monitor.log"

# ==== Logging Function ====
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# ==== LINE Notification Function ====
send_line_notification() {
    local message="$1"
    local response_file="/tmp/line_response.json"
    local http_code
    
    # Escape special characters for JSON
    # Replace newlines with \\n, escape quotes and backslashes
    message=$(echo "$message" | sed ':a;N;$!ba;s/\n/\\n/g' | sed 's/"/\\"/g' | sed 's/\\/\\\\/g')
    
    # Send request and capture HTTP code
    http_code=$(curl -X POST https://api.line.me/v2/bot/message/push \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer ${ACCESS_TOKEN}" \
      -d '{
            "to": "'"${TO}"'",
            "messages": [
              {
                "type": "text",
                "text": "'"${message}"'"
              }
            ]
          }' \
      --silent \
      --output "$response_file" \
      --write-out "%{http_code}")
    
    # Check HTTP response code
    if [ "$http_code" = "200" ]; then
        log "LINE notification sent successfully (HTTP $http_code)"
        rm -f "$response_file"
    else
        log "Failed to send LINE notification (HTTP $http_code)"
        if [ -f "$response_file" ]; then
            log "Response: $(cat "$response_file")"
            rm -f "$response_file"
        fi
    fi
}

# ==== Check SSL Certificate Function ====
check_ssl_certificate() {
    local domain="$1"
    local test_result
    local error_found=false
    local error_message=""
    
    log "Checking SSL certificate for domain: $domain"
    
    # Test ACME.sh renewal with dry-run
    test_result=$(/root/.acme.sh/acme.sh --renew -d "$domain" --force --staging 2>&1)
    local exit_code=$?
    
    # Check for common error patterns
    if echo "$test_result" | grep -qi "timeout\|firewall\|connection\|failed\|error"; then
        error_found=true
        error_message=$(echo "$test_result" | grep -i "error\|timeout\|failed" | head -3)
    fi
    
    # Check exit code
    if [ $exit_code -ne 0 ]; then
        error_found=true
        if [ -z "$error_message" ]; then
            error_message="ACME.sh returned exit code: $exit_code"
        fi
    fi
    
    # Check recent logs for errors (last 24 hours)
    if [ -f "$ACME_LOG" ]; then
        recent_errors=$(grep "$(date -d '1 day ago' '+%Y-%m-%d')\|$(date '+%Y-%m-%d')" "$ACME_LOG" | grep -i "error\|failed\|timeout" | grep "$domain")
        if [ -n "$recent_errors" ]; then
            error_found=true
            if [ -z "$error_message" ]; then
                error_message="Recent errors found in ACME log"
            fi
        fi
    fi
    
    if [ "$error_found" = true ]; then
        log "ERROR: SSL certificate issue detected for $domain"
        log "Error details: $error_message"
        return 1
    else
        log "OK: SSL certificate check passed for $domain"
        return 0
    fi
}

# ==== Main Monitoring Function ====
main() {
    local failed_domains=()
    local error_details=""
    
    log "Starting SSL certificate monitoring on $SERVER_NAME"
    
    # Check each domain
    for domain in "${DOMAINS[@]}"; do
        if ! check_ssl_certificate "$domain"; then
            failed_domains+=("$domain")
        fi
        sleep 2  # Brief pause between checks
    done
    
    # Send notification if any domain failed
    if [ ${#failed_domains[@]} -gt 0 ]; then
        log "SSL certificate issues detected. Sending LINE notification..."
        
        # Get recent error details from log
        if [ -f "$ACME_LOG" ]; then
            error_details=$(tail -20 "$ACME_LOG" | grep -i "error\|timeout\|failed" | tail -3 | tr '\n' ' ')
        fi
        
        # Compose notification message
        local message="🚨 SSL Certificate Alert - $SERVER_NAME

❌ Failed Domains: ${failed_domains[*]}

⚠️ Issues Detected:
- ACME.sh SSL renewal failed
- Let's Encrypt validation error
- Possible firewall blocking

📅 Time: $(date '+%Y-%m-%d %H:%M:%S')

🔧 Action Required:
- Check network connectivity
- Verify firewall rules for port 80
- Review Let's Encrypt validation

Error: $error_details"

        send_line_notification "$message"
        
        log "SSL monitoring completed with errors for domains: ${failed_domains[*]}"
        exit 1
    else
        log "SSL monitoring completed successfully - all domains OK"
        exit 0
    fi
}

# ==== Execution ====
# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Check if configuration is set
if [ "$ACCESS_TOKEN" = "YOUR_CHANNEL_ACCESS_TOKEN" ] || [ "$TO" = "USER_OR_GROUP_ID" ]; then
    log "ERROR: Please configure ACCESS_TOKEN and TO variables"
    exit 1
fi

# Run main function
main "$@"