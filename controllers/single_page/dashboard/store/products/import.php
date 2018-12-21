<?php
namespace Concrete\Package\CommunityStoreImport\Controller\SinglePage\Dashboard\Store\Products;

use Concrete\Core\Controller\Controller;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Package\Package as Package;
use Concrete\Core\File\File;
use Log;
use Exception;
use Config;

use Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductList;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Concrete\Package\CommunityStore\Src\Attribute\Key\StoreProductKey as StoreProductKey;
use Concrete\Package\CommunityStore\Src\CommunityStore\Group\Group as StoreGroup;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductGroup;


class Import extends DashboardPageController
{
    public $helpers = array('form', 'concrete/asset_library', 'json');

    public function view()
    {
        $this->set('pageTitle', t('Product Import'));
    }

    public function run()
    {
        $this->save();

        $MAX_TIME = Config::get('community_store_import.max_execution_time');
        $MAX_EXECUTION_TIME = ini_get('max_execution_time');
        $MAX_INPUT_TIME = ini_get('max_input_time');
        ini_set('max_execution_time', $MAX_TIME);
        ini_set('max_input_time', $MAX_TIME);
        ini_set('auto_detect_line_endings', TRUE);

        $f = \File::getByID(Config::get('community_store_import.import_file'));
        $fname = $_SERVER['DOCUMENT_ROOT'] . $f->getApprovedVersion()->getRelativePath();

        if (!file_exists($fname) || !is_readable($fname)) {
            $this->error->add(t("Import file not found or is not readable."));
            return;
        }

        if (!$handle = @fopen($fname, 'r')) {
            $this->error->add(t('Cannot open file %s.', $fname));
            return;
        }

        $delim = Config::get('community_store_import.csv.delimiter');
        $delim = ($delim === '\t') ? "\t" : $delim;

        $enclosure = Config::get('community_store_import.csv.enclosure');
        $line_length = Config::get('community_store_import.csv.line_length');

        // Get all headings, first line
        $headings = fgetcsv($handle, $line_length, $delim, $enclosure);

        if ($this->isValid($headings)) {
            $this->error->add(t("Required data missing."));
            return;
        }

        // Get attribute headings
        $attributes = array();
        foreach ($headings as $heading) {
            if (preg_match('/^attr_/', $heading)) {
                $attributes[] = $heading;
            }
        }

        $updated = 0;
        $added = 0;

        while (($csv = fgetcsv($handle, $line_length, $delim, $enclosure)) !== FALSE) {
            if (count($csv) === 1) {
                continue;
            }

            // Make associative arrray
            $row = array_combine($headings, $csv);

            $pGroupNames = explode(',', $row['pProductGroups']);
            $pGroupIDs = array();
            foreach ($pGroupNames as $pGroupName) {
                $pgID = StoreGroup::getByName($pGroupName);
                if (!$pgID instanceof StoreGroup) {
                    $pgID = StoreGroup::add($pGroupName);
                }
                $pGroupIDs[] = $pgID;
            }

            $data = array(
                'pSKU' => $row['pSKU'],
                'pName' => trim($row['pName']),
                'pDesc' => trim($row['pDesc']),
                'pDetail' => trim($row['pDetail']),
                'pPrice' => $row['pPrice'],
                'pSalePrice' => $row['pSalePrice'],
                'pCustomerPrice' => $row['pCustomerPrice'],   // tinyint
                'pPriceMaximum' => $row['pPriceMaximum'],
                'pPriceMinimum' => $row['pPriceMinimum'],
                'pPriceSuggestions' => $row['pPriceSuggestions'],   // longtext
                'pFeatured' => $row['pFeatured'],       // tinyint
                'pQty' => $row['pQty'],
                'pQtyUnlim' => $row['pQtyUnlim'],       // tinyint
                'pBackOrder' => $row['pBackOrder'],     // tinyint
                'pNoQty' => $row['pNoQty'],             // tinyint
                'pTaxable' => $row['pTaxable'],         // tinyint
                // @TODO: don't change product image for updates
                'pfID' => Config::get('community_store_import.default_image'),
                'pActive' => $row['pActive'],           // tinyint
                'pShippable' => $row['pShippable'],     // tinyint
                'pLength' => $row['pLength'],
                'pWidth' => $row['pWidth'],
                'pHeight' => $row['pHeight'],
                'pWeight' => $row['pWeight'],
                'pExclusive' => $row['pExclusive'],
                'pProductGroups' => $pGroupIDs,

                // CS v1.4.2
                'pMaxQty' => $row['pMaxQty'],                       // string, not-null
                'pQtyLabel' => $row['pQtyLabel'],                   // string, not-null
                'pAllowDecimalQty' => $row['pAllowDecimalQty'],     // tinyint, not-null
                'pQtySteps' => $row['pQtySteps'],
                'pSeparateShip' => $row['pSeparateShip'],           // tinyint

                // Not imported
                'pTaxClass' => 1,               // Default tax class
                'pNumberItems' => null,
                'pCreateUserAccount' => true,   // tinyint
                'pAutoCheckout' => false,       // tinyint
                'pVariations' => false,         // tinyint
                'pQuantityPrice' => false       // tinyint
            );

            $p = Product::getBySKU($row['pSKU']);
            if ($p instanceof Product) {
                $updated++;
                $data['pID'] = $p->getID();
            } else {
                $added++;
            }

            // Add product
            $p = Product::saveProduct($data);

            // Add product attributes
            foreach ($attributes as $attr) {
                $ak = preg_replace('/^attr_/', '', $attr);
                if (StoreProductKey::getByHandle($ak)) {
                    $p->setAttribute($ak, $row[$attr]);
                }
            }

            // Add groups
            ProductGroup::addGroupsForProduct($data, $p);

            // @TODO: dispatch events - see Products::save()
        }

        $this->set('success', $this->get('success') . "Import completed: $added products added, $updated products updated.");
        Log::addNotice($this->get('success'));

        ini_set('auto_detect_line_endings', FALSE);
        ini_set('max_execution_time', $MAX_EXECUTION_TIME);
        ini_set('max_input_time', $MAX_INPUT_TIME);
    }

    public function save()
    {
        $data = $this->post();

        // @TODO: Validate post data

        Config::save('community_store_import.import_file', $data['import_file']);
        Config::save('community_store_import.default_image', $data['default_image']);
        Config::save('community_store_import.max_execution_time', $data['max_execution_time']);
        Config::save('community_store_import.csv.delimiter', $data['delimiter']);
        Config::save('community_store_import.csv.enclosure', $data['enclosure']);
        Config::save('community_store_import.csv.line_length', $data['line_length']);
    }

    private function isValid($headings)
    {
        // @TODO: implement

        // @TODO: interrogate database for non-null fields
        $dbname = Config::get('database.connections.concrete.database');

        /*
            SELECT GROUP_CONCAT(column_name) nonnull_columns
            FROM information_schema.columns
            WHERE table_schema = '$dbname'
                AND table_name = 'CommunityStoreProducts'
                AND is_nullable = 'NO'
                AND column_name not in ('pID', 'pDateAdded');
        */

        return (false);
    }
}
