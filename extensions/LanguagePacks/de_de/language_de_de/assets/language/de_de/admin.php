﻿<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright (c) 2008, 2009, 2010 PhreeSoft, LLC                   |
// | http://www.PhreeSoft.com                                        |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// |                                                                 |
// | The license that is bundled with this package is located in the |
// | file: /doc/manual/ch01-Introduction/license.html.               |
// | If not, see http://www.gnu.org/licenses/                        |
// +-----------------------------------------------------------------+
//  Path: /modules/assets/language/en_us/admin.php
//

// Module information
define ("MODULE_ASSETS_TITLE", "Assets Modul");
define ("MODULE_ASSETS_DESCRIPTION", "Das Vermögen Modul behält eine Aufzeichnung der Firma Eigentum Das Modul ermöglicht die Schaffung von zusätzlichen Registerkarten und Felder für die Anpassung an die Bedürfnisse der Nutzer..");

// Überschriften
define ("BOX_ASSETS_ADMIN", "Assets Modul Administration");
define ("TEXT_ASSET_FIELDS", "Custom Fields Asset");
define ("TEXT_ASSET_TABS", "Custom Asset Tabs");

// Allgemeine
define ("TEXT_NEW_TAB", "Neue Asset Tab");
define ("TEXT_EDIT_TAB", "Edit Asset Tab");
define ("TEXT_NEW_FIELD", "Neue Asset Field");
define ("TEXT_EDIT_FIELD", "Edit Asset Field");
define ("TEXT_SGL_PREC", "Single Precision");
define ("TEXT_DBL_PREC", "Double Precision");
define ("INV_TEXT_GREATER_THAN", "Größer als");
define ("ASSETS_INFO_CATEGORY_NAME", "Asset Tab Name <br/> Name sollte kurz sein (10) mit keine Sonderzeichen oder Leerzeichen enthalten.");
define ("ASSETS_INFO_INSERT_INTRO", "Bitte geben Sie die neue Registerkarte mit seinen Eigenschaften");
define ("ASSETS_INFO_DELETE_INTRO", "Sind Sie sicher, dass Sie diese Registerkarte löschen \n Tabs kann nicht gelöscht werden, wenn es eine Asset-Feld innerhalb der Registerkarte werden?.");
define ("ASSETS_INFO_DELETE_ERROR", "Diese Registerkarte Name bereits vorhanden ist, verwenden Sie bitte einen anderen Namen.");
define ("ASSETS_TABS_LOG", "Asset Tabs ( %s)");
// Felder
define ("INV_FIELD_NAME", "Field Name:");
define ("INV_CATEGORY_MEMBER", "Tab Mitglied:");
define ("INV_LABEL_DEFAULT_TEXT_VALUE", "Default-Wert:");
define ("INV_LABEL_MAX_NUM_CHARS", "Maximale Anzahl der Zeichen (Länge)");
define ("INV_LABEL_FIXED_255_CHARS", "Fixed auf maximal 255 Zeichen");
define ("INV_LABEL_MAX_255", "(für Längen von weniger als 256 Zeichen)");
define ("INV_LABEL_CHOICES", "Auswahl String eingeben");
define ("INV_LABEL_TEXT_FIELD", "Textfeld");
define ("INV_LABEL_HTML_TEXT_FIELD", "HTML-Code");
define ("INV_LABEL_HYPERLINK", "Hyper-Link");
define ("INV_LABEL_IMAGE_LINK", "Name der Bilddatei");
define ("INV_LABEL_INVENTORY_LINK", "Inventory Link (Link zeigt auf eine andere Inventargegenstand (URL ))");
define ("INV_LABEL_INTEGER_FIELD", "integer Anzahl");
define ("INV_LABEL_INTEGER_RANGE", "Integer Range");
define ("INV_LABEL_DECIMAL_FIELD", "Dezimalklassifikation");
define ("INV_LABEL_DECIMAL_RANGE", "Dezimalbereich");
define ("INV_LABEL_DEFAULT_DISPLAY_VALUE", "Anzeigeformat (Max, Decimal)");
define ("INV_LABEL_DROP_DOWN_FIELD", "Dropdown-Liste");
define ("INV_LABEL_RADIO_FIELD", "Radio-Button-Auswahl <br/> Auswahl eingeben und durch Komma getrennt als: <br/> Wert1: desc1: Def1, Wert2: desc2: def2 <br/> <U>: </ u> <br/> value = Der Wert, der in die Datenbank <br/> desc = Textbeschreibung der Wahl <br/> def = Default 0 oder 1, 1 Stelle ist der Standardwert Wahl <br/> Hinweis: Nur 1 Standardwert ist erlaubt pro Liste ");
define ("INV_LABEL_DATE_TIME_FIELD", "Datum und Zeit");
define ("INV_LABEL_CHECK_BOX_FIELD", "Check Box (Ja oder Nein Choice)");
define ("INV_LABEL_TIME_STAMP_FIELD", "Time Stamp");
define ("INV_LABEL_TIME_STAMP_VALUE", "Feld System zur letzten Datum und Uhrzeit eine Änderung an einem bestimmten Gegenstand im Inventar wurde zum Titel.");
define ("INV_FIELD_NAME_RULES", "Feldnamen nicht enthalten können Leer-oder Sonderzeichen und muss 64 Zeichen lang sein.");

?>