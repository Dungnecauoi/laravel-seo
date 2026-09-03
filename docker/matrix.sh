#!/usr/bin/env bash
# Runs the test suite across the supported Laravel and PHP versions.
#
# Everything happens inside throwaway containers: the source is mounted
# read-only and copied to /app, so the host's vendor/ and composer.lock are
# never touched and nothing has to be installed on the machine.
#
#   docker/matrix.sh              # whole matrix
#   docker/matrix.sh 9 10         # only these Laravel majors
#   docker/matrix.sh --clean      # remove the images afterwards

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_PREFIX="laravel-seo-test"

# laravel | php | testbench
#
# Laravel 9, 10 and 11 are absent on purpose. All three are past their security
# EOL (Feb 2024, Feb 2025, Mar 2026) and every published release carries
# unpatched advisories, so Composer refuses to install them at all — not as a
# warning, as a hard resolver failure. A matrix row for them would test nothing
# except our ability to disable Composer's security policy.
MATRIX=(
  "12|8.2|^10.0"
  "12|8.3|^10.0"
  "12|8.4|^10.0"
  "13|8.3|^11.0"
  "13|8.4|^11.0"
)

if [[ "${1:-}" == "--clean" ]]; then
  echo "Removing test images…"
  docker images --format '{{.Repository}}:{{.Tag}}' | grep "^${IMAGE_PREFIX}:" | xargs -r docker rmi -f
  exit 0
fi

FILTER=("$@")
matches() {
  [[ ${#FILTER[@]} -eq 0 ]] && return 0
  local want
  for want in "${FILTER[@]}"; do [[ "$1" == "$want" ]] && return 0; done
  return 1
}

declare -a RESULTS=()
FAILED=0

for row in "${MATRIX[@]}"; do
  IFS='|' read -r LARAVEL PHP TESTBENCH <<< "$row"
  matches "$LARAVEL" || continue

  IMAGE="${IMAGE_PREFIX}:${PHP}"
  LABEL="Laravel ${LARAVEL} · PHP ${PHP}"

  if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
    echo "==> Building image for PHP ${PHP}"
    if ! docker build -q --build-arg "PHP_VERSION=${PHP}" -t "$IMAGE" "${ROOT}/docker" >/dev/null; then
      echo "!!  No PHP ${PHP} image available — skipping"
      RESULTS+=("SKIP|${LABEL}|no php:${PHP}-cli image")
      continue
    fi
  fi

  echo "==> ${LABEL}"

  # Values go in as environment variables and the script body is single-quoted:
  # interpolating them would let the outer shell eat the backslash in
  # \Illuminate, and the run would fail for a reason that has nothing to do
  # with the package.
  OUTPUT=$(docker run --rm \
    -v "${ROOT}:/src:ro" \
    -e "TESTBENCH=${TESTBENCH}" \
    "$IMAGE" \
    bash -c '
      set -e
      # Copy rather than work in the mount: the host keeps its own vendor/
      # and composer.lock, and nothing written here survives the container.
      cp -r /src /app_src && cd /app_src
      rm -rf vendor composer.lock .git

      composer require --dev --no-update "orchestra/testbench:${TESTBENCH}" -q
      composer update --prefer-dist --no-progress -q
      php -v | head -1
      composer show laravel/framework 2>/dev/null | grep -m1 "^versions" | sed "s/^versions : \*/Laravel /"
      vendor/bin/phpunit
    ' 2>&1)

  STATUS=$?
  SUMMARY=$(echo "$OUTPUT" | grep -aoE 'OK \([0-9]+ tests?, [0-9]+ assertions?\)|Tests: [0-9]+.*' | tail -1)
  VERSIONS=$(echo "$OUTPUT" | grep -E '^(PHP [0-9]|Laravel [0-9])' | tr '\n' ' ')

  if [[ $STATUS -eq 0 ]]; then
    echo "    ${VERSIONS}${SUMMARY}"
    RESULTS+=("PASS|${LABEL}|${SUMMARY}")
  else
    echo "$OUTPUT" | tail -30
    RESULTS+=("FAIL|${LABEL}|${SUMMARY:-see output above}")
    FAILED=1
  fi
done

echo
echo "──────────────────────────────────────────────────────────────"
for result in "${RESULTS[@]}"; do
  IFS='|' read -r STATE LABEL NOTE <<< "$result"
  printf '  %-5s %-24s %s\n' "$STATE" "$LABEL" "$NOTE"
done
echo "──────────────────────────────────────────────────────────────"

exit $FAILED
