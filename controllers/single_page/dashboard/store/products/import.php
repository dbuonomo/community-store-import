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
    private $attributes = array();

    public function view()
    {
        $this->set('pageTitle', t('Product Import'));
    }

    public function run()
    {
        $this->saveSettings();

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

        // Get headings
        $csv = fgetcsv($handle, $line_length, $delim, $enclosure);
        $headings = array_map('strtolower', $csv);

        if ($this->isValid($headings)) {
            $this->error->add(t("Required data missing."));
            return;
        }

        // Get attribute headings
        foreach ($headings as $heading) {
            if (preg_match('/^attr_/', $heading)) {
                $this->attributes[] = $heading;
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

            $p = Product::getBySKU($row['psku']);
            
            if ($p instanceof Product) {
                $this->update($p, $row);
                $updated++;
            } else {
                $p = $this->add($row);
                $added++;
            }

            // @TODO: dispatch events - see Products::save()
        }

        $this->set('success', $this->get('success') . "Import completed: $added products added, $updated products updated.");
        Log::addNotice($this->get('success'));

        ini_set('auto_detect_line_endings', FALSE);
        ini_set('max_execution_time', $MAX_EXECUTION_TIME);
        ini_set('max_input_time', $MAX_INPUT_TIME);
    }

    private function add($row)
    {
        $data = array(
            'pSKU' => $row['psku'],
            'pName' => $row['pname'],
            'pDesc' => trim($row['pdesc']),
            'pDetail' => trim($row['pdetail']),
            'pCustomerPrice' => $row['pcustomerprice'],
            'pFeatured' => $row['pfeatured'],
            'pQty' => $row['pqty'],
            'pNoQty' => $row['pnoqty'],
            'pTaxable' => $row['ptaxable'],
            'pActive' => $row['pactive'],
            'pShippable' => $row['pshippable'],
            'pCreateUserAccount' => $row['pcreateuseraccount'],
            'pAutoCheckout' => $row['pautocheckout'],
            'pExclusive' => $row['pexclusive'],

            'pPrice' => $row['pprice'],
            'pSalePrice' => $row['psaleprice'],
            'pPriceMaximum' => $row['ppricemaximum'],
            'pPriceMinimum' => $row['ppriceminimum'],
            'pPriceSuggestions' => $row['ppricesuggestions'],
            'pQtyUnlim' => $row['pqtyunlim'],
            'pBackOrder' => $row['pbackorder'],
            'pLength' => $row['plength'],
            'pWidth' => $row['pwidth'],
            'pHeight' => $row['pheight'],
            'pWeight' => $row['pweight'],
            'pNumberItems' => $row['pnumberitems'],

            // CS v1.4.2+
            'pMaxQty' => $row['pmaxqty'],
            'pQtyLabel' => $row['pqtylabel'],
            'pAllowDecimalQty' => $row['pallowdecimalqty'],
            'pQtySteps' => $row['pqtysteps'],
            'pSeperateShip' => $row['pseperateship'],
            'pPackageData' => $row['ppackagedata'],

            // Not supported in CSV data
            'pfID' => Config::get('community_store_import.default_image'),
            'pVariations' => false,
            'pQuantityPrice' => false,
            'pTaxClass' => 1        // 1 = default tax class
        );

        // Save product
        $p = Product::saveProduct($data);

        // Add product attributes
        foreach ($this->attributes as $attr) {
            $ak = preg_replace('/^attr_/', '', $attr);
            if (StoreProductKey::getByHandle($ak)) {
                $p->setAttribute($ak, $row[$attr]);
            }
        }
        
        $pGroupNames = explode(',', $row['pproductgroups']);
        $pGroupIDs = array();
        foreach ($pGroupNames as $pGroupName) {
            $pgID = StoreGroup::getByName($pGroupName);
            if (!$pgID instanceof StoreGroup) {
                $pgID = StoreGroup::add($pGroupName);
            }
            $pGroupIDs[] = $pgID;
        }
        $data['pProductGroups'] = $pGroupIDs;

        // Add groups
        ProductGroup::addGroupsForProduct($data, $p);

        return $p;
    }

    private function update($p, $row)
    {
        if ($row['psku']) $p->setSKU($row['psku']);
        if ($row['pname']) $p->setName($row['pname']);
        if ($row['pdesc']) $p->setDescription($row['pdesc']);
        if ($row['pdetail']) $p->setDetail($row['pdetail']);
        if ($row['pfeatured']) $p->setIsFeatured($row['pfeatured']);
        if ($row['pqty']) $p->setQty($row['pqty']);
        if ($row['pnoqty']) $p->setNoQty($row['pnoqty']);
        if ($row['ptaxable']) $p->setISTaxable($row['ptaxable']);
        if ($row['pactive']) $p->setIsActive($row['pqctive']);
        if ($row['pshippable']) $p->setIsShippable($row['pshippable']);
        if ($row['pcreateuseraccount']) $p->setCreatesUserAccount($row['pcreateuseraccount']);
        if ($row['pautocheckout']) $p->setAutoCheckout($row['pautocheckout']);
        if ($row['pexclusive']) $p->setIsExclusive($row['pexclusive']);

        if ($row['pprice']) $p->setPrice($row['pprice']);
        if ($row['psaleprice']) $p->setSalePrice($row['psaleprice']);
        if ($row['ppricemaximum']) $p->setPriceMaximum($row['ppricemaximum']);
        if ($row['ppriceminimum']) $p->setPriceMinimum($row['ppriceminimum']);
        if ($row['ppricesuggestions']) $p->setPriceSuggestions($row['ppricesuggestions']);
        if ($row['pqtyunlim']) $p->setIsUnlimited($row['pqtyunlim']);
        if ($row['pbackorder']) $p->setAllowBackOrder($row['pbackorder']);
        if ($row['plength']) $p->setLength($row['plength']);
        if ($row['pwidth']) $p->setWidth($row['pwidth']);
        if ($row['pheight']) $p->setHeight($row['pheight']);
        if ($row['pweight']) $p->setWeight($row['pweight']);
        if ($row['pnumberitems']) $p->setNumberItems($row['pnumberitems']);
        
        // CS v1.4.2+
        if ($row['pmaxqty']) $p->setMaxQty($row['pmaxqty']);
        if ($row['pqtylabel']) $p->setQtyLabel($row['pqtylabel']);
        if ($row['pallowdecimalqty']) $p->setAllowDecimalQty($row['pallowdecimalqty']);
        if ($row['pqtysteps']) $p->setQtySteps($row['pqtysteps']);
        if ($row['pseparateship']) $p->setSeparateShip($row['pseparateship']);
        if ($row['ppackagedata']) $p->setPackageData($row['ppackagedata']);

        // Product attributes
        foreach ($this->attributes as $attr) {
            $ak = preg_replace('/^attr_/', '', $attr);
            if (StoreProductKey::getByHandle($ak)) {
                $p->setAttribute($ak, $row[$attr]);
            }
        }

        // Product groups
        if ($row['pproductgroups']) {
            $pGroupNames = explode(',', $row['pproductgroups']);
            $pGroupIDs = array();
            foreach ($pGroupNames as $pGroupName) {
                $pgID = StoreGroup::getByName($pGroupName);
                if (!$pgID instanceof StoreGroup) {
                    $pgID = StoreGroup::add($pGroupName);
                }
                $pGroupIDs[] = $pgID;
            }
            $data['pProductGroups'] = $pGroupIDs;

            // Update groups
            ProductGroup::addGroupsForProduct($data, $p);
        }

        $p = $p->save();

        return $p;
    }
   
    private function saveSettings()
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
                // pfID is excluded because it is not-null but also an optional field
                AND column_name not in ('pID', 'pfID', pDateAdded');
        */

        return (false);
    }
}

