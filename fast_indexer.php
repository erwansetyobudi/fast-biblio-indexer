#!/usr/bin/env php
<?php
/**
 * FAST BIBLIO INDEXER for SLiMS
 * Optimized for performance dengan batch processing
 * 
 * Usage: php fast_indexer.php [option]
 * Options: 
 *   --empty     : Kosongkan tabel search_biblio
 *   --index     : Jalankan indexing
 *   --batch=100 : Jumlah record per batch (default: 100)
 *   --help      : Tampilkan bantuan
 */

define('INDEX_AUTH', '1');
require 'sysconfig.inc.php';
require SIMBIO . 'simbio_DB/simbio_dbop.inc.php';

class FastBiblioIndexer
{
    private $db;
    private $batchSize = 100;
    private $totalProcessed = 0;
    private $totalFailed = 0;
    private $startTime;
    
    public function __construct($dbs)
    {
        $this->db = $dbs;
        $this->startTime = microtime(true);
        
        // Optimasi MySQL untuk indexing cepat
        $this->db->query("SET SESSION bulk_insert_buffer_size = 8388608");
        $this->db->query("SET SESSION unique_checks = 0");
        $this->db->query("SET SESSION foreign_key_checks = 0");
    }
    
    public function setBatchSize($size)
    {
        $this->batchSize = (int)$size;
    }
    
    public function emptyIndex()
    {
        echo "Mengosongkan tabel search_biblio... ";
        $this->db->query("TRUNCATE TABLE search_biblio");
        echo "OK!\n";
    }
    
    public function getTotalBiblio()
    {
        $result = $this->db->query("SELECT COUNT(*) as total FROM biblio");
        $data = $result->fetch_assoc();
        return $data['total'];
    }
    
    public function runIndexing()
    {
        $totalBiblio = $this->getTotalBiblio();
        echo "\n========================================\n";
        echo " FAST BIBLIO INDEXER\n";
        echo "========================================\n";
        echo "Total bibliografi: " . number_format($totalBiblio) . " records\n";
        echo "Batch size: " . $this->batchSize . " records/batch\n";
        echo "----------------------------------------\n\n";
        
        $offset = 0;
        $batchNumber = 1;
        
        while ($offset < $totalBiblio) {
            echo "[Batch " . $batchNumber . "] Memproses record " . ($offset + 1) . " - " . min($offset + $this->batchSize, $totalBiblio) . "... ";
            
            $this->processBatch($offset, $this->batchSize);
            
            $offset += $this->batchSize;
            $batchNumber++;
            
            // Tampilkan progress
            $percentage = ($offset / $totalBiblio) * 100;
            echo sprintf(" %.1f%% selesai\n", $percentage);
            
            // Flush output
            ob_flush();
            flush();
        }
        
        $this->showSummary($totalBiblio);
    }
    
    private function processBatch($offset, $limit)
    {
        // Query JOIN yang efisien untuk mengambil semua data sekaligus
        $sql = "
            SELECT 
                b.biblio_id,
                b.title,
                b.edition,
                b.isbn_issn,
                b.publish_year,
                b.classification,
                b.spec_detail_info,
                b.notes,
                b.series_title,
                b.call_number,
                b.opac_hide,
                b.promoted,
                b.labels,
                b.collation,
                b.image,
                b.input_date,
                b.last_update,
                g.gmd_name AS gmd,
                p.publisher_name AS publisher,
                pl.place_name AS publish_place,
                l.language_name AS language,
                ct.content_type,
                mt.media_type,
                crt.carrier_type,
                
                -- Author (digabung jadi satu string)
                (
                    SELECT GROUP_CONCAT(DISTINCT ma.author_name SEPARATOR ' - ')
                    FROM biblio_author ba
                    LEFT JOIN mst_author ma ON ba.author_id = ma.author_id
                    WHERE ba.biblio_id = b.biblio_id
                    ORDER BY ba.level ASC
                ) AS author,
                
                -- Topic (digabung jadi satu string)
                (
                    SELECT GROUP_CONCAT(DISTINCT mt.topic SEPARATOR ' - ')
                    FROM biblio_topic bt
                    LEFT JOIN mst_topic mt ON bt.topic_id = mt.topic_id
                    WHERE bt.biblio_id = b.biblio_id
                ) AS topic,
                
                -- Location (unique locations)
                (
                    SELECT GROUP_CONCAT(DISTINCT l.location_name SEPARATOR ' - ')
                    FROM item i
                    LEFT JOIN mst_location l ON i.location_id = l.location_id
                    WHERE i.biblio_id = b.biblio_id
                ) AS location,
                
                -- Items (barcode)
                (
                    SELECT GROUP_CONCAT(DISTINCT i.item_code SEPARATOR ' - ')
                    FROM item i
                    WHERE i.biblio_id = b.biblio_id
                ) AS items,
                
                -- Collection Types
                (
                    SELECT GROUP_CONCAT(DISTINCT ct.coll_type_name SEPARATOR ' - ')
                    FROM item i
                    LEFT JOIN mst_coll_type ct ON i.coll_type_id = ct.coll_type_id
                    WHERE i.biblio_id = b.biblio_id
                ) AS collection_types
                
            FROM biblio b
            LEFT JOIN mst_gmd g ON b.gmd_id = g.gmd_id
            LEFT JOIN mst_publisher p ON b.publisher_id = p.publisher_id
            LEFT JOIN mst_place pl ON b.publish_place_id = pl.place_id
            LEFT JOIN mst_language l ON b.language_id = l.language_id
            LEFT JOIN mst_content_type ct ON b.content_type_id = ct.id
            LEFT JOIN mst_media_type mt ON b.media_type_id = mt.id
            LEFT JOIN mst_carrier_type crt ON b.carrier_type_id = crt.id
            LIMIT $offset, $limit
        ";
        
        $result = $this->db->query($sql);
        
        if (!$result || $result->num_rows == 0) {
            echo "Tidak ada data\n";
            return;
        }
        
        // Gunakan INSERT dengan multiple rows untuk efisiensi
        $values = [];
        $failedIds = [];
        
        while ($row = $result->fetch_assoc()) {
            // Escape data untuk keamanan
            $escaped = $this->escapeRow($row);
            $values[] = "(
                {$escaped['biblio_id']},
                '{$escaped['title']}',
                '{$escaped['edition']}',
                '{$escaped['isbn_issn']}',
                '{$escaped['author']}',
                '{$escaped['topic']}',
                '{$escaped['gmd']}',
                '{$escaped['publisher']}',
                '{$escaped['publish_place']}',
                '{$escaped['language']}',
                '{$escaped['classification']}',
                '{$escaped['spec_detail_info']}',
                '{$escaped['carrier_type']}',
                '{$escaped['content_type']}',
                '{$escaped['media_type']}',
                '{$escaped['location']}',
                '{$escaped['publish_year']}',
                '{$escaped['notes']}',
                '{$escaped['series_title']}',
                '{$escaped['items']}',
                '{$escaped['collection_types']}',
                '{$escaped['call_number']}',
                {$escaped['opac_hide']},
                {$escaped['promoted']},
                '{$escaped['labels']}',
                '{$escaped['collation']}',
                '{$escaped['image']}',
                '{$escaped['input_date']}',
                '{$escaped['last_update']}'
            )";
        }
        
        if (!empty($values)) {
            $insertSql = "INSERT INTO search_biblio (
                biblio_id, title, edition, isbn_issn, author, topic, gmd, 
                publisher, publish_place, language, classification, spec_detail_info,
                carrier_type, content_type, media_type, location, publish_year, 
                notes, series_title, items, collection_types, call_number, 
                opac_hide, promoted, labels, collation, image, input_date, last_update
            ) VALUES " . implode(",\n", $values) . "
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                edition = VALUES(edition),
                isbn_issn = VALUES(isbn_issn),
                author = VALUES(author),
                topic = VALUES(topic),
                gmd = VALUES(gmd),
                publisher = VALUES(publisher),
                publish_place = VALUES(publish_place),
                language = VALUES(language),
                classification = VALUES(classification),
                spec_detail_info = VALUES(spec_detail_info),
                carrier_type = VALUES(carrier_type),
                content_type = VALUES(content_type),
                media_type = VALUES(media_type),
                location = VALUES(location),
                publish_year = VALUES(publish_year),
                notes = VALUES(notes),
                series_title = VALUES(series_title),
                items = VALUES(items),
                collection_types = VALUES(collection_types),
                call_number = VALUES(call_number),
                opac_hide = VALUES(opac_hide),
                promoted = VALUES(promoted),
                labels = VALUES(labels),
                collation = VALUES(collation),
                image = VALUES(image),
                input_date = VALUES(input_date),
                last_update = VALUES(last_update)
            ";
            
            if ($this->db->query($insertSql)) {
                $this->totalProcessed += count($values);
            } else {
                // Fallback: insert satu-satu jika batch gagal
                echo "\n  Batch gagal, mencoba insert satu-satu... ";
                foreach ($values as $value) {
                    $singleSql = "INSERT INTO search_biblio VALUES $value ON DUPLICATE KEY UPDATE title = VALUES(title)";
                    if ($this->db->query($singleSql)) {
                        $this->totalProcessed++;
                    } else {
                        $this->totalFailed++;
                    }
                }
                echo "Selesai\n";
            }
        }
    }
    
    private function escapeRow($row)
    {
        $escaped = [];
        foreach ($row as $key => $value) {
            if ($value === null) {
                $escaped[$key] = 'NULL';
            } elseif (is_numeric($value) && in_array($key, ['biblio_id', 'opac_hide', 'promoted'])) {
                $escaped[$key] = $value;
            } else {
                $escaped[$key] = $this->db->real_escape_string((string)$value);
            }
        }
        return $escaped;
    }
    
    private function showSummary($total)
    {
        $endTime = microtime(true);
        $duration = $endTime - $this->startTime;
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        
        // Verifikasi hasil
        $check = $this->db->query("SELECT COUNT(*) as indexed FROM search_biblio");
        $indexed = $check->fetch_assoc()['indexed'];
        
        echo "\n========================================\n";
        echo " HASIL INDEXING\n";
        echo "========================================\n";
        echo "Total biblio: " . number_format($total) . " records\n";
        echo "Berhasil diindex: " . number_format($indexed) . " records\n";
        echo "Gagal: " . number_format($this->totalFailed) . " records\n";
        echo "Waktu: {$minutes} menit {$seconds} detik\n";
        echo "Kecepatan: " . number_format($total / $duration, 2) . " records/detik\n";
        echo "========================================\n";
        
        if ($indexed == $total) {
            echo "✓ INDEXING SELESAI DENGAN SUKSES!\n";
        } else {
            echo "⚠ Indexing tidak sempurna. Periksa error log.\n";
        }
    }
}

// ==================== MAIN EXECUTION ====================

// Parse command line arguments
$options = getopt('', ['empty', 'index', 'batch::', 'help']);

if (isset($options['help'])) {
    echo "Penggunaan: php fast_indexer.php [options]\n\n";
    echo "Options:\n";
    echo "  --empty       Kosongkan tabel search_biblio\n";
    echo "  --index       Jalankan indexing\n";
    echo "  --batch=N     Jumlah record per batch (default: 100, max: 500)\n";
    echo "  --help        Tampilkan bantuan ini\n\n";
    echo "Contoh:\n";
    echo "  php fast_indexer.php --empty\n";
    echo "  php fast_indexer.php --index\n";
    echo "  php fast_indexer.php --index --batch=250\n";
    echo "  php fast_indexer.php --empty --index --batch=200\n";
    exit(0);
}

global $dbs;
$indexer = new FastBiblioIndexer($dbs);

// Set batch size
if (isset($options['batch'])) {
    $batch = (int)$options['batch'];
    $batch = min($batch, 500); // Maksimal 500 per batch
    $indexer->setBatchSize($batch);
}

// Run operations
if (isset($options['empty'])) {
    $indexer->emptyIndex();
}

if (isset($options['index'])) {
    $indexer->runIndexing();
} elseif (!isset($options['empty'])) {
    // Jika tidak ada parameter, tampilkan help
    echo "Silakan gunakan --help untuk melihat panduan\n";
}