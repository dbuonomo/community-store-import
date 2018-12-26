# Community Store Import
A concrete5 Community Store package to import products via a CSV file.

## Operation
Note: do not run this utility without first creating a backup of your concrete5 database.

After install, a new dashboard menu option will be available under Store -> Products -> Import.

Example CSV files can be found in the examples directory.

* If an import SKU already exists, the matching product data will be updated.
* Empty rows are skipped.
* Image import is not supported, however a default image can be selected prior to import.

## Compatibility

Tested with the following versions of Community Store:

* v1.3.3
* v1.4.2

## Column Headings
The first row of the CSV file must contain column headings as defined below. Columns headings that do not match any of the prescribed column names will be ignored.

### Supported
Column Name | Default | CS Version
----------- | ------- | ----------
pSKU | | 1.x+
pName | | 1.x+
pDesc | | 1.x+
pDetail | | 1.x+
pPrice | | 1.x+
pFeatured | | 1.x+
pQty | | 1.x+
pNoQty | | 1.x+
pTaxable | | 1.x+
pActive | | 1.x+
pShippable | | 1.x+
pCreateUserAccount | | 1.x+
pAutoCheckout | | 1.x+
pExclusive | | 1.x+
pSalePrice | | 1.x+
pQtyUnlim | | 1.x+
pBackOrder | | 1.x+
pLength | | 1.x+
pWidth | | 1.x+
pHeight | | 1.x+
pWeight | | 1.x+
pNumberItems | | 1.x+
pProductGroups | | 1.x+
pPriceMaximum | | 1.0.7+
pPriceMinimum | | 1.0.7+
pPriceSuggestions | | 1.0.7+
pCustomerPrice | | 1.0.7+
pMaxQty | | 1.4.2+
pQtyLabel | | 1.4.2+
pAllowDecimalQty | | 1.4.2+
pQtySteps | | 1.4.2+
pSeparateShip | | 1.4.2+
pPackageData | | 1.4.2+

### Not supported<sup>1</sup>
Column Name | Default | CS Version
----------- | ------- | ----------
pTaxClass | 1 | 1.x+
pVariations | false | 1.x+
pQuantityPrice | false | 1.x+
pDateAdded | new DateTime() | 1.x+

[1] The default value is applied when the product is added.

## Custom Attribute Fields
Custom attribute field headings must have a format of ‘attr_{attribute handle}’. Example: attr_my_attribute.

Note: Custom attributes will not be created during import. If a custom attribute does not exist prior to import, the custom attribute data will not be added to that product.

## Product Groups
Product groups can be specified by name in a column named pProductGroups. Separate each group name with a comma.  If the product group does not exist, it will be created. This field is optional.

## Roadmap
* Use a queue to process data
* Add a summary and confirmation before import
* Add option to skip product updates
* Support image IDs in CSV data
* Add support for Community Store 2.x
* Dispatch concrete5 events
* Test with older versions of Community Store

