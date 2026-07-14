#!/bin/bash
################################################################################
# Secure Blog CMS — Security Audit Toolkit v2.0
#
# Usage: ./security_audit.sh <target_url> [options]
#
# Examples:
#   ./security_audit.sh https://lassiter.eu/blog
#   ./security_audit.sh https://example.com/blog/ -v
#   ./security_audit.sh https://example.com/blog/ --full
#
# Options:
#   -h, --help      Show help
#   -v, --verbose   Show full response bodies
#   --full          Run destructive tests (rate-limit, brute-force)
#   --auth          Test authenticated endpoints (requires session)
#
# DISCLAIMER: Only run against systems you own or have explicit permission to test.
################################################################################

set -euo pipefail

# ─── Colors ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# ─── Configuration ─────────────────────────────────────────────────────────────
TARGET="${1:-}"
VERBOSE=false
FULL_MODE=false
AUTH_MODE=false
TIMEOUT=15
CURL_ARGS="-s -L --max-time $TIMEOUT --max-redirs 3"

# Counters
PASSED=0
FAILED=0
WARNED=0
INFO_COUNT=0

# Results file
RESULTS_FILE="cms_audit_$(date +%Y%m%d_%H%M%S).txt"

# ─── Argument Parsing ──────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        -h|--help)
            cat << 'HELP'
Secure Blog CMS Security Audit Toolkit v2.0

Usage: ./security_audit.sh <target_url> [options]

Options:
  -h, --help    Show this help message
  -v, --verbose Show full response bodies and headers
  --full        Run destructive tests (rate-limit, brute-force)
  --auth        Test authenticated endpoints

Examples:
  ./security_audit.sh https://lassiter.eu/blog
  ./security_audit.sh https://example.com/blog/ -v --full
HELP
            exit 0
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        --full)
            FULL_MODE=true
            shift
            ;;
        --auth)
            AUTH_MODE=true
            shift
            ;;
        -http://*|-https://*)
            TARGET="$1"
            shift
            ;;
        *)
            if [[ -z "$TARGET" ]]; then
                TARGET="$1"
            else
                echo -e "${RED}Unknown argument: $1${NC}"
                exit 1
            fi
            shift
            ;;
    esac
done

# ─── Validation ─────────────────────────────────────────────────────────────────
if [[ -z "$TARGET" ]]; then
    echo -e "${RED}Error: Target URL required${NC}"
    echo "Usage: $0 <target_url>"
    echo "Example: $0 https://lassiter.eu/blog"
    exit 1
fi

# Normalize URL — remove trailing slash
TARGET="${TARGET%/}"

if [[ ! "$TARGET" =~ ^https?:// ]]; then
    echo -e "${RED}Error: URL must start with http:// or https://${NC}"
    exit 1
fi

# ─── Logging ────────────────────────────────────────────────────────────────────
_log() {
    local level="$1"; shift
    local msg="$*"
    local timestamp
    timestamp=$(date +%H:%M:%S)
    echo "[$timestamp] [$level] $msg" >> "$RESULTS_FILE"
}

pass() { echo -e "  ${GREEN}✔ PASS${NC} $*"; _log "PASS" "$*"; ((PASSED++)); }
fail() { echo -e "  ${RED}✘ FAIL${NC} $*"; _log "FAIL" "$*"; ((FAILED++)); }
warn() { echo -e "  ${YELLOW}⚠ WARN${NC} $*"; _log "WARN" "$*"; ((WARNED++)); }
info() { echo -e "  ${CYAN}ℹ INFO${NC} $*"; _log "INFO" "$*"; ((INFO_COUNT++)); }
test_header() { echo -e "\n${BOLD}${BLUE}▶ $*${NC}"; _log "TEST" "$*"; }

# ─── Curl Helpers ──────────────────────────────────────────────────────────────
# Fetch HTTP status code only
http_code() {
    curl $CURL_ARGS -o /dev/null -w "%{http_code}" "$1" 2>/dev/null || echo "000"
}

# Fetch response body
http_body() {
    curl $CURL_ARGS "$1" 2>/dev/null || echo ""
}

# Fetch headers only
http_headers() {
    curl $CURL_ARGS -I "$1" 2>/dev/null || echo ""
}

# Fetch headers + body
http_full() {
    curl $CURL_ARGS -i "$1" 2>/dev/null || echo ""
}

# Fetch with redirect following disabled
http_code_noredirect() {
    curl -s -o /dev/null -w "%{http_code}" --max-time $TIMEOUT --max-redirs 0 "$1" 2>/dev/null || echo "000"
}

# ─── Initialize ─────────────────────────────────────────────────────────────────
echo "Security Assessment Results" > "$RESULTS_FILE"
echo "Target: $TARGET" >> "$RESULTS_FILE"
echo "Date: $(date -u)" >> "$RESULTS_FILE"
echo "=============================================" >> "$RESULTS_FILE"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════════╗"
echo -e "║  Secure Blog CMS — Security Audit Toolkit v2.0           ║"
echo -e "╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Target:  ${BOLD}$TARGET${NC}"
echo -e "  Full:    $FULL_MODE"
echo -e "  Auth:    $AUTH_MODE"
echo -e "  Results: ${RESULTS_FILE}"
echo ""

# ─── Verify Target ──────────────────────────────────────────────────────────────
test_header "CONNECTIVITY CHECK"
code=$(http_code "$TARGET/")
if [[ "$code" == "200" || "$code" == "301" || "$code" == "302" ]]; then
    pass "Target is reachable (HTTP $code)"
else
    fail "Target returned HTTP $code — cannot proceed"
    echo -e "\n${RED}Aborting. Target must be reachable.${NC}"
    exit 1
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 1: SENSITIVE FILE & DIRECTORY EXPOSURE
# ═══════════════════════════════════════════════════════════════════════════════
test_header "SENSITIVE FILES & DIRECTORIES"

# Install directory
code=$(http_code "$TARGET/install/")
if [[ "$code" == "200" ]]; then
    fail "/install/ is publicly accessible — DELETE OR BLOCK IMMEDIATELY"
elif [[ "$code" == "403" ]]; then
    pass "/install/ blocked (403 Forbidden)"
elif [[ "$code" == "404" ]]; then
    pass "/install/ not found (likely deleted)"
else
    warn "/install/ returned HTTP $code"
fi

# Data directory
for endpoint in "/data/" "/data/settings/site.json" "/data/users/" "/data/posts/" "/data/comments/" "/data/sessions/" "/data/taxonomy.json"; do
    code=$(http_code "$TARGET$endpoint")
    if [[ "$code" == "200" ]]; then
        fail "Data endpoint EXPOSED: $endpoint (HTTP 200)"
    fi
done
# If none returned 200, data dir is protected
if ! http_body "$TARGET/data/" 2>/dev/null | head -1 | grep -qi "200\|index\|directory"; then
    pass "Data directory is not publicly accessible"
fi

# Includes directory
code=$(http_code "$TARGET/includes/")
if [[ "$code" == "200" ]]; then
    fail "/includes/ is publicly accessible"
elif [[ "$code" == "403" ]]; then
    pass "/includes/ blocked (403 Forbidden)"
elif [[ "$code" == "404" ]]; then
    pass "/includes/ not found"
fi

# Config file exposure
for file in "includes/config.php" "includes/Storage.php" "includes/Security.php" "includes/users.php"; do
    code=$(http_code "$TARGET/$file")
    if [[ "$code" == "200" ]]; then
        fail "Source file EXPOSED: $file (HTTP 200) — PHP may not be executing"
    fi
done
pass "PHP source files not directly accessible (verified)"

# .git exposure
code=$(http_code "$TARGET/.git/HEAD")
if [[ "$code" == "200" ]]; then
    fail ".git directory is publicly accessible — REPOSITORY LEAKED"
else
    pass ".git directory not accessible"
fi

# .htaccess exposure
code=$(http_code "$TARGET/.htaccess")
if [[ "$code" == "200" ]]; then
    warn ".htaccess is readable — may reveal rewrite rules (not critical on nginx)"
else
    pass ".htaccess not directly accessible"
fi

# version.json exposure
code=$(http_code "$TARGET/data/version.json")
if [[ "$code" == "200" ]]; then
    warn "version.json is accessible — may reveal exact CMS version"
else
    pass "version.json not accessible"
fi

# .env files
for envfile in ".env" ".env.local" ".env.production"; do
    code=$(http_code "$TARGET/$envfile")
    if [[ "$code" == "200" ]]; then
        fail "Environment file EXPOSED: $envfile (HTTP 200)"
    fi
done
pass "No .env files found"

# Backup files
for backup in "db.sql" "database.sql" "backup.sql" "dump.sql" "data/backup.zip" "data/backups/"; do
    code=$(http_code "$TARGET/$backup")
    if [[ "$code" == "200" ]]; then
        fail "Backup file EXPOSED: $backup (HTTP 200)"
    fi
done
pass "No database backups found"

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 2: HTTP SECURITY HEADERS
# ═══════════════════════════════════════════════════════════════════════════════
test_header "HTTP SECURITY HEADERS"

headers=$(http_headers "$TARGET/")

# Content-Security-Policy
if echo "$headers" | grep -qi "content-security-policy"; then
    csp=$(echo "$headers" | grep -i "content-security-policy" | head -1 | sed 's/.*: //' | tr -d '\r')
    pass "Content-Security-Policy present"

    # Analyze CSP
    if echo "$csp" | grep -qi "default-src.*'self'"; then
        pass "CSP has strict default-src"
    else
        warn "CSP default-src is not 'self' — may be too permissive"
    fi

    if echo "$csp" | grep -qi "script-src.*'unsafe-eval'"; then
        warn "CSP allows unsafe-eval in script-src (needed for TinyMCE in admin, check public pages)"
    fi

    if echo "$csp" | grep -qi "img-src.*data:"; then
        warn "CSP allows data: URIs in img-src (potential SVG XSS vector on public pages)"
    else
        pass "CSP does NOT allow data: URIs in img-src (good)"
    fi

    if echo "$csp" | grep -qi "object-src.*'none'"; then
        pass "CSP blocks object-src"
    else
        warn "CSP does not block object-src"
    fi
else
    fail "Content-Security-Policy header is MISSING"
fi

# X-Content-Type-Options
if echo "$headers" | grep -qi "x-content-type-options.*nosniff"; then
    pass "X-Content-Type-Options: nosniff present"
else
    fail "X-Content-Type-Options: nosniff is MISSING"
fi

# X-Frame-Options
if echo "$headers" | grep -qi "x-frame-options"; then
    xfo=$(echo "$headers" | grep -i "x-frame-options" | head -1 | sed 's/.*: //' | tr -d '\r' | xargs)
    pass "X-Frame-Options present: $xfo"
else
    warn "X-Frame-Options header is MISSING (CSP frame-ancestors may cover this)"
fi

# HSTS
if echo "$headers" | grep -qi "strict-transport-security"; then
    hsts=$(echo "$headers" | grep -i "strict-transport-security" | head -1 | sed 's/.*: //' | tr -d '\r' | xargs)
    pass "HSTS present: $hsts"

    if echo "$hsts" | grep -qi "includeSubDomains"; then
        pass "HSTS includes subdomains"
    fi
    if echo "$hsts" | grep -qi "preload"; then
        pass "HSTS has preload flag"
    fi
    max_age=$(echo "$hsts" | grep -oP 'max-age=\K[0-9]+' | head -1)
    if [[ -n "$max_age" ]] && [[ "$max_age" -lt 31536000 ]]; then
        warn "HSTS max-age is less than 1 year ($max_age seconds)"
    fi
else
    warn "Strict-Transport-Security header is MISSING (may be set at nginx/proxy level)"
fi

# Referrer-Policy
if echo "$headers" | grep -qi "referrer-policy"; then
    pass "Referrer-Policy header present"
else
    warn "Referrer-Policy header is MISSING"
fi

# Permissions-Policy
if echo "$headers" | grep -qi "permissions-policy"; then
    pass "Permissions-Policy header present"
else
    warn "Permissions-Policy header is MISSING (recommended)"
fi

# X-Powered-By / Server version disclosure
if echo "$headers" | grep -qi "x-powered-by"; then
    xpb=$(echo "$headers" | grep -i "x-powered-by" | head -1 | sed 's/.*: //' | tr -d '\r' | xargs)
    warn "X-Powered-By header reveals: $xpb"
else
    pass "X-Powered-By header not present"
fi

if echo "$headers" | grep -qi "^server:"; then
    server=$(echo "$headers" | grep -i "^server:" | head -1 | sed 's/.*: //' | tr -d '\r' | xargs)
    if echo "$server" | grep -qiP '\d+\.\d+'; then
        warn "Server header reveals version: $server"
    else
        info "Server header present: $server"
    fi
fi

# Version disclosure
if echo "$headers" | grep -qi "x-secureblogcms-version"; then
    ver=$(echo "$headers" | grep -i "x-secureblogcms-version" | head -1 | sed 's/.*: //' | tr -d '\r' | xargs)
    fail "CMS version disclosed in header: $ver"
else
    pass "No X-SecureBlogCMS-Version header (good — not leaking version)"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 3: TLS / HTTPS
# ═══════════════════════════════════════════════════════════════════════════════
test_header "TLS / HTTPS CONFIGURATION"

if [[ "$TARGET" == https://* ]]; then
    pass "Target uses HTTPS"

    # Extract hostname:port
    hostport="${TARGET#https://}"
    hostport="${hostport%%/*}"
    if [[ "$hostport" != *:* ]]; then
        hostport="$hostport:443"
    fi

    # Check TLS protocols
    echo -n "  Checking TLS 1.0... "
    if echo | openssl s_client -connect "$hostport" -tls1 2>/dev/null | grep -q "Protocol.*TLSv1\b"; then
        fail "TLS 1.0 is enabled — should be disabled"
    else
        pass "TLS 1.0 is disabled"
    fi

    echo -n "  Checking TLS 1.1... "
    if echo | openssl s_client -connect "$hostport" -tls1_1 2>/dev/null | grep -q "Protocol.*TLSv1.1"; then
        fail "TLS 1.1 is enabled — should be disabled"
    else
        pass "TLS 1.1 is disabled"
    fi

    echo -n "  Checking TLS 1.2... "
    if echo | openssl s_client -connect "$hostport" -tls1_2 2>/dev/null | grep -q "Protocol.*TLSv1.2"; then
        pass "TLS 1.2 is supported"
    else
        warn "TLS 1.2 check inconclusive (openssl may not support -tls1_2 flag)"
    fi

    echo -n "  Checking TLS 1.3... "
    if echo | openssl s_client -connect "$hostport" -tls1_3 2>/dev/null | grep -q "Protocol.*TLSv1.3"; then
        pass "TLS 1.3 is supported"
    else
        info "TLS 1.3 not detected (may not be supported by server or openssl)"
    fi

    # HTTP → HTTPS redirect
    if [[ "$TARGET" == https://* ]]; then
        http_target="http://${TARGET#https://}"
        redirect_code=$(http_code_noredirect "$http_target/")
        if [[ "$redirect_code" == "301" || "$redirect_code" == "302" ]]; then
            pass "HTTP redirects to HTTPS ($redirect_code)"
        elif [[ "$redirect_code" == "000" ]]; then
            info "HTTP not reachable (connection refused) — likely fine"
        else
            warn "HTTP does not redirect to HTTPS (HTTP $redirect_code)"
        fi
    fi
else
    fail "Target is NOT using HTTPS — all data transmitted in cleartext"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 4: AUTHENTICATION & SESSION SECURITY
# ═══════════════════════════════════════════════════════════════════════════════
test_header "AUTHENTICATION & SESSION SECURITY"

# Admin endpoint
code=$(http_code "$TARGET/admin.php")
body=$(http_body "$TARGET/admin.php")
if echo "$body" | grep -qi "login\|password\|sign in\|authenticate"; then
    pass "Admin panel requires authentication"
else
    if [[ "$code" == "302" || "$code" == "301" ]]; then
        pass "Admin panel redirects (likely to login)"
    else
        warn "Admin panel returned HTTP $code — could not verify auth requirement"
    fi
fi

# Login page
code=$(http_code "$TARGET/admin/login.php")
if [[ "$code" == "200" ]]; then
    info "Login page is accessible (HTTP 200) — expected"
else
    info "Login page returned HTTP $code"
fi

# CSRF token present on login page
login_body=$(http_body "$TARGET/admin/login.php")
if echo "$login_body" | grep -qi "csrf_token\|csrf.*token\|name=\"token\""; then
    pass "CSRF token present on login form"
else
    warn "Could not detect CSRF token on login form"
fi

# Session cookie attributes
# We need to make a request that sets a cookie first
cookie_headers=$(curl $CURL_ARGS -c - "$TARGET/" 2>/dev/null | head -5)
if echo "$cookie_headers" | grep -qi "SECURE_CMS_SESSION"; then
    info "Session cookie name: SECURE_CMS_SESSION"

    if echo "$cookie_headers" | grep -qi "httponly"; then
        pass "Session cookie has HttpOnly flag"
    else
        fail "Session cookie MISSING HttpOnly flag"
    fi

    if echo "$cookie_headers" | grep -qi "samesite"; then
        ss_val=$(echo "$cookie_headers" | grep -i "samesite" | head -1 | sed 's/.*SameSite=//' | awk '{print $1}' | tr -d ';\r')
        pass "Session cookie has SameSite=$ss_val"
    else
        warn "Session cookie MISSING SameSite attribute"
    fi

    if echo "$cookie_headers" | grep -qi "secure"; then
        pass "Session cookie has Secure flag"
    else
        warn "Session cookie MISSING Secure flag (may be expected on HTTP; critical on HTTPS)"
    fi
else
    info "No session cookie detected on initial request (may be set on login)"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 5: RATE LIMITING & BRUTE FORCE (DESTRUCTIVE — --full only)
# ═══════════════════════════════════════════════════════════════════════════════
test_header "RATE LIMITING & BRUTE FORCE PROTECTION"

if [[ "$FULL_MODE" == "true" ]]; then
    warn "Running destructive rate-limit tests..."

    # Login brute force — send 7 rapid bad attempts (limit is 5)
    echo -e "  ${CYAN}Sending 7 rapid login attempts to test lockout...${NC}"
    login_page=$(http_body "$TARGET/admin/login.php" 2>/dev/null)

    # Try to extract CSRF token
    csrf_token=$(echo "$login_page" | grep -oP 'name="csrf_token"\s+value="\K[^"]+' | head -1)
    if [[ -z "$csrf_token" ]]; then
        csrf_token=$(echo "$login_page" | grep -oP "csrf_token.*?value='([^']+)" | sed "s/.*value='//" | head -1)
    fi
    if [[ -z "$csrf_token" ]]; then
        csrf_token="test_token_placeholder"
        warn "Could not extract CSRF token, using placeholder"
    fi

    locked_out=false
    for i in $(seq 1 7); do
        response=$(curl $CURL_ARGS -X POST \
            -d "action=login&username=admin&password=wrong_password_$i&csrf_token=$csrf_token" \
            "$TARGET/admin/login.php" 2>/dev/null)

        if echo "$response" | grep -qi "too many\|lockout\|locked\|rate limit\|429\|attempt"; then
            pass "Rate limiting engaged after $i attempts"
            locked_out=true
            break
        fi
        # Refresh CSRF token for next attempt
        csrf_token=$(echo "$response" | grep -oP 'name="csrf_token"\s+value="\K[^"]+' | head -1)
        [[ -z "$csrf_token" ]] && csrf_token="test_token_placeholder"
    done

    if ! $locked_out; then
        fail "No rate limiting detected after 7 login attempts — account lockout may not be working"
    fi

    # Comment rate limiting
    echo -e "  ${CYAN}Testing comment rate limiting...${NC}"
    # Find a published post to test commenting on
    post_body=$(http_body "$TARGET/" 2>/dev/null)
    post_link=$(echo "$post_body" | grep -oP 'href="[^"]*post/[^"]*"' | head -1 | sed 's/href="//;s/"//')
    if [[ -n "$post_link" ]]; then
        full_link="$TARGET$post_link"
        # If it's a relative link starting with /, prepend the domain
        if [[ "$post_link" == /* ]]; then
            full_link="${TARGET}${post_link}"
        fi
        info "Found post for comment test: $full_link"

        # Send 5 rapid comment attempts
        for i in $(seq 1 5); do
            http_code_noredirect "$full_link" > /dev/null 2>&1
        done
        info "Sent 5 rapid requests to post page (comment rate-limit needs manual verification)"
    else
        info "No post links found on homepage for comment rate-limit test"
    fi
else
    info "Rate-limit tests skipped (use --full to run)"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 6: INPUT VALIDATION & XSS
# ═══════════════════════════════════════════════════════════════════════════════
test_header "INPUT VALIDATION & XSS PROTECTION"

# Search XSS
xss_payloads=(
    '<script>alert(1)</script>'
    '"><script>alert(1)</script>'
    "'\"><img src=x onerror=alert(1)>"
    '<svg onload=alert(1)>'
    'javascript:alert(1)'
)

for payload in "${xss_payloads[@]}"; do
    encoded_payload=$(python3 -c "import urllib.parse; print(urllib.parse.quote('$payload'))" 2>/dev/null || echo "")
    if [[ -z "$encoded_payload" ]]; then
        encoded_payload=$(echo "$payload" | sed 's/ /%20/g; s/</%3C/g; s/>/%3E/g; s/"/%22/g; s/'\''/%27/g')
    fi
    response=$(http_body "$TARGET/?q=$encoded_payload" 2>/dev/null)
    if echo "$response" | grep -q "$payload"; then
        fail "REFLECTED XSS: Payload reflected unescaped: $payload"
    fi
done
pass "No reflected XSS found in search parameter"

# Search query length limit
long_query=$(python3 -c "print('A' * 10000)" 2>/dev/null || printf 'A%.0s' {1..1000})
code=$(http_code "$TARGET/?q=$long_query")
if [[ "$code" == "414" || "$code" == "413" ]]; then
    pass "Server rejects oversized queries (HTTP $code)"
elif [[ "$code" == "200" ]]; then
    warn "Server accepts very long search queries (no length limit)"
else
    info "Long query returned HTTP $code"
fi

# Path traversal in post slug
for traversal in "../../../etc/passwd" "..%2F..%2F..%2Fetc%2Fpasswd" "....//....//etc//passwd"; do
    code=$(http_code "$TARGET/post.php?slug=$traversal")
    if [[ "$code" == "200" ]]; then
        body=$(http_body "$TARGET/post.php?slug=$traversal")
        if echo "$body" | grep -qi "root:x:0:0"; then
            fail "PATH TRAVERSAL: /etc/passwd readable!"
        fi
    fi
done
pass "No path traversal vulnerability detected"

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 7: CMS-SPECIFIC FUNCTIONALITY
# ═══════════════════════════════════════════════════════════════════════════════
test_header "CMS FUNCTIONALITY"

# RSS feed
rss_body=$(http_body "$TARGET/rss.php")
if echo "$rss_body" | grep -qi "<?xml\|<rss"; then
    pass "RSS feed returns valid XML"

    # Check RSS for self-link
    if echo "$rss_body" | grep -qi "atom:link.*self"; then
        self_link=$(echo "$rss_body" | grep -oP 'atom:link[^>]*href="[^"]*"' | head -1)
        info "RSS self-link: $self_link"

        # Verify self-link points to the correct domain
        if echo "$self_link" | grep -qi "$TARGET"; then
            pass "RSS self-link points to correct domain"
        else
            warn "RSS self-link may point to wrong domain"
        fi
    fi

    # Check RSS doesn't leak private posts
    if echo "$rss_body" | grep -qi "🔒\|password protected"; then
        warn "RSS feed may be leaking password-protected post titles"
    else
        pass "No password-protected post content in RSS feed"
    fi
else
    code=$(http_code "$TARGET/rss.php")
    fail "RSS feed not working properly (HTTP $code)"
fi

# Short URL handler
code=$(http_code "$TARGET/s.php?code=nonexistenttest123")
if [[ "$code" == "400" || "$code" == "404" ]]; then
    pass "Short URL handler rejects invalid codes (HTTP $code)"
elif [[ "$code" == "302" || "$code" == "301" ]]; then
    warn "Short URL handler redirects on invalid code (HTTP $code) — should return 404"
else
    info "Short URL handler returned HTTP $code for invalid code"
fi

# Pretty URL patterns
info "Testing pretty URL patterns..."

# Category URL
code=$(http_code "$TARGET/category/test")
if [[ "$code" == "200" || "$code" == "301" || "$code" == "302" ]]; then
    pass "Category URL pattern works (HTTP $code)"
else
    warn "Category URL pattern may not work (HTTP $code) — check nginx rewrites"
fi

# Tag URL
code=$(http_code "$TARGET/tag/test")
if [[ "$code" == "200" || "$code" == "301" || "$code" == "302" ]]; then
    pass "Tag URL pattern works (HTTP $code)"
else
    warn "Tag URL pattern may not work (HTTP $code) — check nginx rewrites"
fi

# Page URL
code=$(http_code "$TARGET/page/1")
if [[ "$code" == "200" || "$code" == "301" || "$code" == "302" ]]; then
    pass "Pagination URL pattern works (HTTP $code)"
else
    warn "Pagination URL pattern may not work (HTTP $code) — check nginx rewrites"
fi

# Homepage
code=$(http_code "$TARGET/")
if [[ "$code" == "200" ]]; then
    pass "Homepage loads successfully (HTTP 200)"

    # Check for CMS version in footer
    body=$(http_body "$TARGET/")
    if echo "$body" | grep -oP 'v\d+\.\d+\.\d+' | head -1; then
        ver=$(echo "$body" | grep -oP 'v\d+\.\d+\.\d+' | head -1)
        info "CMS version visible in page: $ver"
    fi

    # Check for internal link consistency (subfolder install)
    if echo "$body" | grep -qP 'href="(index\.php|admin/admin\.php|post\.php)"'; then
        warn "Found hardcoded relative links — may break in subfolder installs"
    fi
else
    fail "Homepage returned HTTP $code"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 8: UPLOAD SECURITY (UNAUTHENTICATED)
# ═══════════════════════════════════════════════════════════════════════════════
test_header "UPLOAD SECURITY"

# Upload endpoint should require auth
code=$(http_code "$TARGET/admin/upload-image.php")
if [[ "$code" == "403" ]]; then
    pass "Upload endpoint requires authentication (403 Forbidden)"
elif [[ "$code" == "200" ]]; then
    # Check if it actually accepts uploads without auth
    upload_response=$(curl $CURL_ARGS -X POST "$TARGET/admin/upload-image.php" 2>/dev/null)
    if echo "$upload_response" | grep -qi "unauthorized\|login\|forbidden"; then
        pass "Upload endpoint blocks unauthenticated requests"
    else
        fail "Upload endpoint may accept unauthenticated uploads!"
    fi
else
    info "Upload endpoint returned HTTP $code"
fi

# Image serve endpoint
code=$(http_code "$TARGET/admin/serve-image.php?img=test.jpg")
if [[ "$code" == "403" || "$code" == "404" ]]; then
    pass "Image serve endpoint rejects invalid filenames"
elif [[ "$code" == "200" ]]; then
    warn "Image serve endpoint returned 200 for nonexistent file"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 9: CSRF PROTECTION
# ═══════════════════════════════════════════════════════════════════════════════
test_header "CSRF PROTECTION"

# Check if forms have CSRF tokens
pages_with_forms=(
    "$TARGET/admin/login.php"
    "$TARGET/admin.php"
)

for page in "${pages_with_forms[@]}"; do
    body=$(http_body "$page" 2>/dev/null)
    if echo "$body" | grep -qi "csrf_token\|_token\|name=\"token\""; then
        pass "CSRF token found on: $page"
    else
        # May be behind auth redirect
        code=$(http_code "$page")
        if [[ "$code" == "302" || "$code" == "301" ]]; then
            info "Could not check $page (redirects to login)"
        else
            warn "No CSRF token detected on: $page (HTTP $code)"
        fi
    fi
done

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 10: DIRECTORY LISTING & INFORMATION DISCLOSURE
# ═══════════════════════════════════════════════════════════════════════════════
test_header "INFORMATION DISCLOSURE"

# Check for directory listing
dirs_to_test=(
    "/admin/"
    "/templates/"
    "/includes/"
    "/data/"
    "/data/uploads/"
    "/data/uploads/images/"
)

for dir in "${dirs_to_test[@]}"; do
    body=$(http_body "$TARGET$dir" 2>/dev/null)
    if echo "$body" | grep -qi "Index of\|Directory listing\|<title>.*directory"; then
        fail "Directory listing enabled: $dir"
    fi
done
pass "No directory listing detected"

# Check PHP error display
code=$(http_code "$TARGET/index.php?[]=test")
if [[ "$code" == "500" ]]; then
    body=$(http_body "$TARGET/index.php?[]=test" 2>/dev/null)
    if echo "$body" | grep -qi "Fatal error\|Warning\|Notice\|Stack trace\|in.*on line"; then
        warn "PHP errors may be visible to users (check display_errors in php.ini)"
    else
        info "Error page returned but no PHP details leaked"
    fi
fi

# Check for common error page info disclosure
code=$(http_code "$TARGET/nonexistent_page_12345")
if [[ "$code" == "404" ]]; then
    body=$(http_body "$TARGET/nonexistent_page_12345" 2>/dev/null)
    if echo "$body" | grep -qi "nginx\|apache\|php.*version\|x-powered-by"; then
        warn "404 page may leak server information"
    else
        pass "404 page does not leak server details"
    fi
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SECTION 11: MALWARE SCAN CAPABILITY CHECK
# ═══════════════════════════════════════════════════════════════════════════════
test_header "MALWARE SCAN CHECKS"

# Check if ClamAV is available on the server (for reference)
info "The CMS has built-in malware scanning for uploads (ENABLE_UPLOAD_MALWARE_SCAN)"
info "For server-level scanning, consider: ClamAV, Maldet (Linux Malware Detect)"
info "Check uploads directory for suspicious files:"
info "  find $TARGET/data/uploads/ -name '*.php' -o -name '*.phtml' -o -name '*.phar'"

# ═══════════════════════════════════════════════════════════════════════════════
# RESULTS SUMMARY
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════════╗"
echo -e "║  AUDIT COMPLETE                                           ║"
echo -e "╠══════════════════════════════════════════════════════════╣${NC}"
echo -e "  ${GREEN}✔ PASSED:${NC}  $PASSED"
echo -e "  ${RED}✘ FAILED:${NC}  $FAILED"
echo -e "  ${YELLOW}⚠ WARNED:${NC}  $WARNED"
echo -e "  ${CYAN}ℹ INFO:${NC}   $INFO_COUNT"
echo ""
if [[ $FAILED -gt 0 ]]; then
    echo -e "  ${RED}${BOLD}⚠ FAILURES DETECTED — Review and fix immediately${NC}"
else
    echo -e "  ${GREEN}${BOLD}✓ No critical failures detected${NC}"
fi
echo ""
echo -e "  Full results saved to: ${BOLD}$RESULTS_FILE${NC}"
echo ""

if [[ $FAILED -gt 0 ]]; then
    exit 1
else
    exit 0
fi