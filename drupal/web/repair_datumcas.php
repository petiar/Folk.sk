<?php
// Repair script: obnov field_datumcas z D6 databázy
$db    = \Drupal::database();
$d6_db = \Drupal\Core\Database\Database::getConnection('default', 'migrate');

// Skontroluj aktuálny stav
$count = $db->select('node__field_datumcas', 'f')->countQuery()->execute()->fetchField();
echo "Aktuálny počet záznamov v node__field_datumcas: $count\n";

// Načítaj z D6
$d6_rows = $d6_db->query(
  "SELECT nid, field_datumcas_value FROM content_type_akcia WHERE field_datumcas_value IS NOT NULL"
)->fetchAllKeyed();
echo "D6 dátumov: " . count($d6_rows) . "\n";

// Migrate mapa D6 nid → D11 nid
$map = $db->select('migrate_map_d6_node_complete__akcia', 'm')
  ->fields('m', ['sourceid1', 'destid1'])
  ->execute()
  ->fetchAllKeyed();
echo "Migrate mapa: " . count($map) . " záznamov\n";

// Revision mapa D11 nid → vid + langcode
$rev_map = [];
foreach ($db->select('node_field_data', 'n')->fields('n', ['nid', 'vid', 'langcode'])->condition('type', 'akcia')->execute()->fetchAll() as $r) {
  $rev_map[$r->nid] = ['vid' => $r->vid, 'langcode' => $r->langcode];
}

// Zmaž existujúce (prázdne alebo chybné) záznamy
$db->truncate('node__field_datumcas')->execute();
$db->truncate('node_revision__field_datumcas')->execute();
echo "Tabuľky vyčistené.\n";

$inserted = 0;
foreach ($d6_rows as $d6_nid => $d6_value) {
  $d11_nid = $map[$d6_nid] ?? NULL;
  if (!$d11_nid || !isset($rev_map[$d11_nid])) continue;

  $value = str_replace(' ', 'T', $d6_value);
  $data  = [
    'bundle'                   => 'akcia',
    'deleted'                  => 0,
    'entity_id'                => $d11_nid,
    'revision_id'              => $rev_map[$d11_nid]['vid'],
    'langcode'                 => $rev_map[$d11_nid]['langcode'],
    'delta'                    => 0,
    'field_datumcas_value'     => $value,
    'field_datumcas_end_value' => $value,
  ];
  $db->insert('node__field_datumcas')->fields($data)->execute();
  $db->insert('node_revision__field_datumcas')->fields($data)->execute();
  $inserted++;
}

echo "Vložených: $inserted dátumov.\n";
echo "Hotovo. Spusti: drush cache:rebuild\n";
