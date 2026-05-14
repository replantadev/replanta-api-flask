-- SQL para encontrar taxonomías de BetterDocs
-- Ejecutar en phpMyAdmin o MySQL CLI

-- Ver todas las taxonomías registradas con posts
SELECT DISTINCT taxonomy 
FROM wp_term_taxonomy 
WHERE taxonomy LIKE '%doc%' 
   OR taxonomy LIKE '%knowledge%'
   OR taxonomy LIKE '%better%';

-- Ver categorías BetterDocs con conteo
SELECT 
    t.term_id,
    t.name,
    tt.taxonomy,
    tt.count as num_docs
FROM wp_terms t
INNER JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
WHERE tt.taxonomy LIKE '%doc%'
ORDER BY tt.count DESC;

-- Ver si hay metas generadas
SELECT 
    tm.term_id,
    t.name as categoria,
    tm.meta_key,
    LEFT(tm.meta_value, 100) as meta_preview
FROM wp_termmeta tm
INNER JOIN wp_terms t ON tm.term_id = t.term_id
WHERE tm.meta_key = 'replanta_betterdocs_meta_description'
ORDER BY tm.term_id;
