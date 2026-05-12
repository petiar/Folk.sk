#!/usr/bin/env bash
# Migrácia folk.sk: Drupal 6 → Drupal 11
# Spúšťaj z koreňa projektu (kde je .lando.yml)
#
# Použitie:
#   bash migrate.sh            # spustí všetky kroky
#   bash migrate.sh users      # len migrácia používateľov
#   bash migrate.sh taxonomy   # len taxonómia
#   bash migrate.sh content    # len nody + komentáre
#   bash migrate.sh files      # len súbory
#   bash migrate.sh aliases    # len URL aliasy

set -euo pipefail

DRUSH="lando ssh -c 'cd /app/drupal/web && php ../vendor/drush/drush/drush.php"

drush() {
  lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php $*"
}

ok()   { echo "  ✓ $*"; }
info() { echo ""; echo "==> $*"; }
fail() { echo "  ✗ CHYBA: $*"; exit 1; }

run_migration() {
  local id="$1"
  local label="${2:-$1}"
  echo -n "  Migrujem $label... "
  output=$(lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php migrate:import '$id' 2>&1" || true)
  if echo "$output" | grep -q "failed\|Error"; then
    echo "UPOZORNENIE"
    echo "$output" | grep -E "error|Error|failed" | head -5
  else
    created=$(echo "$output" | grep -oP '\d+ created' | head -1 || echo "?")
    updated=$(echo "$output" | grep -oP '\d+ updated' | head -1 || echo "")
    echo "OK ($created${updated:+, $updated})"
  fi
}

# ─── JAZYK ───────────────────────────────────────────────────────────────────
migrate_language() {
  info "Jazyk"
  lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php pm:enable language locale -y 2>/dev/null" | grep -E "success|already" || true
  lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php language:add sk 2>/dev/null" | grep -E "Added|already" || true
  lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php config:set system.site default_langcode sk -y 2>/dev/null" || true
  ok "Slovenčina nastavená ako default"
}

# ─── PREREKVIZITY ────────────────────────────────────────────────────────────
check_prerequisites() {
  info "Kontrola prerekvizít"

  # Modul folk_migrate musí byť povolený (obsahuje config override pre picture path)
  status=$(lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php pm:list --filter=folk_migrate --field=status 2>/dev/null" || echo "")
  if ! echo "$status" | grep -qi "enabled"; then
    echo -n "  Povoľujem folk_migrate... "
    lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php pm:enable folk_migrate migrate migrate_drupal migrate_plus migrate_tools -y 2>&1" | tail -1
    ok "folk_migrate povolený"
  else
    ok "folk_migrate je povolený"
  fi

  # Overenie spojenia so zdrojovou D6 DB
  echo -n "  Overujem spojenie s D6 DB... "
  result=$(lando ssh -c "php -r \"\\\$p=new PDO('mysql:host=db.r5.websupport.sk;port=3317;dbname=drupal_folk_sk','drupal_folk_sk','thrud404'); echo \\\$p->query('SELECT COUNT(*) FROM users')->fetchColumn();\"" 2>/dev/null || echo "0")
  if [ "$result" -gt 0 ] 2>/dev/null; then
    ok "D6 DB dostupná ($result používateľov)"
  else
    fail "Nie je možné sa pripojiť na D6 DB. Skontroluj settings.php (kľúč 'migrate')."
  fi
}

# ─── FILTER FORMÁTY (závislosť pre roly) ─────────────────────────────────────
migrate_filters() {
  info "Filter formáty"
  run_migration "d6_filter_format" "filter formáty"
}

# ─── POUŽÍVATELIA ─────────────────────────────────────────────────────────────
migrate_users() {
  info "Používatelia"

  # Roly
  run_migration "d6_user_role" "roly"

  # Konfigurácia user picture poľa
  run_migration "user_picture_field"                "user picture field storage"
  run_migration "user_picture_field_instance"       "user picture field instance"
  run_migration "user_picture_entity_display"       "user picture display"
  run_migration "user_picture_entity_form_display"  "user picture form display"

  # Konfigurácia profil polí
  run_migration "user_profile_field"                "profil field storage"
  run_migration "user_profile_field_instance"       "profil field instance"
  run_migration "user_profile_entity_display"       "profil display"
  run_migration "user_profile_entity_form_display"  "profil form display"

  # DÔLEŽITÉ: najprv súbory (avatáre), potom users — aby sa linky nastavili správne
  run_migration "d6_user_picture_file"      "avatáre (súbory)"
  run_migration "d6_user"                   "používatelia (1308)"
  run_migration "d6_profile_values"         "hodnoty profil polí"
  run_migration "d6_user_contact_settings"  "kontaktné nastavenia"
  run_migration "d6_user_mail"              "email šablóny"
  run_migration "d6_user_settings"          "nastavenia"
}

# ─── TAXONÓMIA ────────────────────────────────────────────────────────────────
migrate_taxonomy() {
  info "Taxonómia"
  # d6_vocabulary_field_instance závisí od d6_node_type — spustíme ho tu ak ešte nebeží
  run_migration "d6_node_type"                    "node types (závislosť)"
  run_migration "d6_taxonomy_vocabulary"          "slovníky (12)"
  run_migration "d6_vocabulary_field"             "vocabulary field storage"
  run_migration "d6_vocabulary_field_instance"    "vocabulary field instance"
  run_migration "d6_vocabulary_entity_display"    "vocabulary display"
  run_migration "d6_vocabulary_entity_form_display" "vocabulary form display"
  run_migration "d6_taxonomy_term"                "termíny (1645)"
}

# ─── SÚBORY ───────────────────────────────────────────────────────────────────
migrate_files() {
  info "Súbory"
  run_migration "d6_file"                       "spravované súbory (833)"
  run_migration "d6_upload_field"               "upload field storage"
  run_migration "d6_upload_field_instance"      "upload field instance"
  run_migration "d6_upload_entity_display"      "upload display"
  run_migration "d6_upload_entity_form_display" "upload form display"
}

# ─── POLIA (CCK) ──────────────────────────────────────────────────────────────
migrate_fields() {
  info "CCK polia"
  run_migration "d6_field"                       "field storage definície (31)"
  run_migration "d6_field_instance"              "field instance definície (37)"
  run_migration "d6_field_formatter_settings"    "formatter nastavenia"
  run_migration "d6_field_instance_widget_settings" "widget nastavenia"
  run_migration "d6_view_modes"                  "view modes"
}

# ─── NODY ─────────────────────────────────────────────────────────────────────
migrate_content() {
  info "Content types (node type konfigurácia)"
  run_migration "d6_node_type"            "node types (17)"  # idempotentné, môže bežať aj z migrate_taxonomy
  run_migration "d6_node_settings"        "node nastavenia"
  run_migration "d6_node_setting_promote" "node promote nastavenia"
  run_migration "d6_node_setting_status"  "node status nastavenia"
  run_migration "d6_node_setting_sticky"  "node sticky nastavenia"
  run_migration "d6_comment_type"         "comment types"
  run_migration "d6_comment_field"        "comment field storage"
  run_migration "d6_comment_field_instance"          "comment field instance"
  run_migration "d6_comment_entity_display"          "comment display"
  run_migration "d6_comment_entity_form_display"     "comment form display"
  run_migration "d6_comment_entity_form_display_subject" "comment subject display"

  info "Nody"
  for type in akcia blog book clanok forum katalog page pesnicka poll pozvanka profil profil_blog redakcny_stlpcek simplenews sprava televizia video; do
    run_migration "d6_node_complete:${type}" "${type}"
  done

  info "Komentáre (5377)"
  run_migration "d6_comment" "komentáre"
}

# ─── URL ALIASY ───────────────────────────────────────────────────────────────
migrate_aliases() {
  info "URL aliasy (16600)"
  run_migration "d6_url_alias" "URL aliasy"
}

# Bloky a menu — zámerne vynechané, nastaví sa nanovo pri dizajne

# ─── HLAVNÝ BEŽEC ─────────────────────────────────────────────────────────────
STEP="${1:-all}"

check_prerequisites

case "$STEP" in
  users)
    migrate_filters
    migrate_users
    ;;
  taxonomy)
    migrate_taxonomy
    ;;
  files)
    migrate_files
    ;;
  content)
    migrate_fields
    migrate_content
    ;;
  aliases)
    migrate_aliases
    ;;
  all)
    migrate_language
    migrate_filters
    migrate_users
    migrate_taxonomy
    migrate_files
    migrate_fields
    migrate_content
    migrate_aliases
    ;;
  *)
    echo "Neznámy krok: $STEP"
    echo "Použitie: bash migrate.sh [users|taxonomy|files|content|aliases|all]"
    exit 1
    ;;
esac

echo ""
echo "Hotovo! Skontroluj výsledky: lando ssh -c \"cd /app/drupal/web && php ../vendor/drush/drush/drush.php migrate:status --format=table\""