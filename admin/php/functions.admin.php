<?
    session_start();
    $url = explode("?", $_SERVER["REQUEST_URI"], 5);

    $page_array = explode("/admin/", $url[0]);
    $page = rtrim($page_array[1], "/");
    

    class ObjectParam {
        public $dbName;
        public $rusName;
        public $required = false;
        public $type = "string"; // string, rich, integer, float, select, checkbox, photo, photos, file, encrypted, map
        public $maxLength = false;
        public $thisTable = true;
        public $onChange = false;
        public $onClick = false;
        public $onInit = false;
        public $onAddInit = false;
        public $onEditInit = false;
        public $formatter = false;
        public $defValue = false;
        public $editable = true;
        public $render = false;
        public $editHidden = true;
        public $width = 120;
    }

    class ObjectTable {
        public $leftJoins;
        public $colArray;
        public $dbName;
        public $where;
        public $jqGridCustom;
        public $editOnly = false;
        public $readOnly = false;

        function __construct($tableName) {
            $this->dbName = $tableName;
            $this->colArray = $this->getTableColArray();
            $this->leftJoins = $this->getTableLeftJoins();
            $this->where = $this->getTableWhere();
            $this->jqGridCustom = $this->getjqGridCustom();
        }

        private function getMainTableCol($colName, $prefix) {
            $colObject = new ObjectParam();
            $colObject->dbName = "NL_" . $prefix . "_" . substr($colName, 3);
            switch ($colName) {
                // ОБЩИЕ ПОЛЯ
                case "ID":
                    $colObject->dbName = "ID_NL_" . $prefix;
                    $colObject->rusName = "И/н";
                    $colObject->type = "integer";
                    $colObject->render = true;
                    $colObject->editable = true;
                    $colObject->editHidden = false;
                    $colObject->defValue = 'function(){
                        var defVal = "";
                        $.ajax({
                            url: "/admin/php/set_id.php",
                            data: {
                                "table": "' . $this->dbName . '"
                            },
                            method: "POST",
                            async: false,
                            success: function (data) {
                                defVal = $.trim(data);
                            }
                        });
                        return defVal;
                    }';
                    $colObject->width = 40;
                    break;
                case "ID_NL_USER":
                    $colObject->dbName = "ID_NL_USER";
                    $colObject->rusName = "Ответственный";
                    $colObject->type = "select";
                    $colObject->defValue = $_SESSION["ID_NL_USER"];
                    if ($_SESSION["ID_NL_USER_PERMISSION"] == "2") {
                        $colObject->editable = true;
                    } elseif ($this->dbName != "NL_USER") {
                        $colObject->editable = true;
                        $colObject->editHidden = false;
                    } else {
                        $colObject->editable = false;
                    }
                    $colObject->render = true;
                    break;
                case "NL_SHORT":
                    $colObject->rusName = "Кратко";
                    $colObject->required = true;
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 25;
                    break;
                case "NL_FULL":
                    $colObject->rusName = "Полное имя";
                    $colObject->required = true;
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 2550;
                    break;
                case "NL_PHONE":
                    $colObject->rusName = "Телефон";
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 50;
                    $colObject->defValue = $_SESSION["NL_USER_PHONE"];
                    break;
                case "NL_PHONE_OWNER":
                    $colObject->rusName = "Контакт собственника";
                    $colObject->type = "string";
                    $colObject->render = false;
                    $colObject->maxLength = 255;
                    break;

                // СПРАВОЧНИКОВ ПОЛЬЗОВАТЕЛЕЙ
                case "ID_NL_USER_PERMISSION":
                    $colObject->dbName = "ID_NL_USER_PERMISSION";
                    $colObject->rusName = "Права";
                    $colObject->type = "select";
                    $colObject->render = true;
                    $colObject->defValue = 1;
                    $colObject->editable = true;
                    $colObject->editHidden = false;
                    break;
                case "NL_LOGIN":
                    $colObject->rusName = "Логин (на англ.)";
                    $colObject->required = true;
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 50;
                    break;
                case "NL_PASSWORD":
                    $colObject->rusName = "Пароль";
                    $colObject->required = true;
                    $colObject->type = "encrypted";
                    break;

                // СПРАВОЧНИКИ
                case "NL_PROP_IS_ACTIVE":
                    $colObject->rusName = "Объект активен?";
                    $colObject->type = "checkbox";
                    $colObject->render = true;
                    $colObject->required = true;
                    $colObject->defValue = '"Нет"';
                    break;

                // ЖУРНАЛЫ
                case "ID_NL_VIEW":
                    $colObject->dbName = "ID_NL_VIEW";
                    $colObject->rusName = "Вид из окна";
                    $colObject->type = "select";
                    $colObject->render = true;
                    break;
                case "NL_FLOOR":
                    $colObject->rusName = "Этаж";
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 25;
                    $colObject->width = 40;
                    break;
                case "NL_AREA_FULL":
                    $colObject->rusName = "Площадь (общая)";
                    $colObject->type = "float";
                    $colObject->render = true;
                    $colObject->required = true;
                    $colObject->width = 40;
                    break;
                case "NL_PHOTO_URLS":
                    $colObject->rusName = "Фотографии";
                    $colObject->type = "photos";
                    $colObject->render = true;
                    break;
                case "NL_COST_TOTAL":
                    $colObject->rusName = "Общая стоимость";
                    $colObject->type = "integer";
                    $colObject->render = true;
                    $colObject->width = 80;
                    break;
                case "NL_ADDRESS":
                    $colObject->rusName = "Адрес";
                    $colObject->type = "map";
                    $colObject->render = true;
                    $colObject->maxLength = 2550;
                    break;
                case "NL_DESCRIPTION":
                    $colObject->rusName = "Описание";
                    $colObject->type = "rich";
                    $colObject->render = false;
                    $colObject->required = true;
                    $colObject->maxLength = 5100;
                    break;
            }

            return $colObject;
        }

        private function getTableColArrayByColumnNames($tableName, $colNames) {
            $table = Array();

            array_push($table, $this->getMainTableCol("ID", $tableName));
            for ($i = 0; $i < count($colNames); $i++) {
                $colName = $colNames[$i];
                $colNameName = $colName;
                if (strpos($colName, "ID_") === false) {
                    $colNameName = mb_ereg_replace($tableName . "_", "", $colName);
                }
                array_push($table, $this->getMainTableCol($colNameName, $tableName));
            }

            $table["ID_" . $tableName] = $this->getMainTableCol("ID", $tableName);
            for ($i = 0; $i < count($colNames); $i++) {
                $colName = $colNames[$i];
                $colNameName = $colName;
                if (strpos($colName, "ID_") === false) {
                    $colNameName = mb_ereg_replace($tableName . "_", "", $colName);
                }
                $table[$colName] = $this->getMainTableCol($colNameName, $tableName);
            }

            return $table;
        }

        private function getTableColArray() {
            $table = Array();

            $tableName = substr($this->dbName, 3);
            switch ($this->dbName) {
                case "NL_USER":
                    $colNames = ["ID_NL_USER_PERMISSION", "NL_LOGIN", "NL_PASSWORD", "NL_SHORT", "NL_FULL", "NL_PHONE"];
                    $table = $this->getTableColArrayByColumnNames($tableName, $colNames);
                    break;
                case "NL_VIEW":
                    $colNames = ["NL_VIEW_SHORT"];
                    $table = $this->getTableColArrayByColumnNames($tableName, $colNames);
                    break;
                case "NL_PROP_RESALE":
                    $colNames = ["NL_PROP_RESALE_AREA_FULL", "NL_PROP_RESALE_ADDRESS", "NL_PROP_RESALE_FLOOR", "NL_PROP_RESALE_COST_TOTAL", "NL_PROP_RESALE_PHONE_OWNER", "ID_NL_VIEW", "ID_NL_USER", "NL_PROP_RESALE_PHONE", "NL_PROP_RESALE_PHOTO_URLS", "NL_PROP_RESALE_DESCRIPTION"];
                    $table = $this->getTableColArrayByColumnNames($tableName, $colNames);
                    break;
            }

            return $table;
        }

        private function getTableLeftJoins() {
            switch ($this->dbName) {
                case "NL_USER":
                    return ["NL_USER_PERMISSION"];
                    break;
                case "NL_PROP_RESALE":
                    return ["NL_VIEW", "NL_USER"];
                    break;
                default:
                    return [];
                    break;
            }
        }

        private function getTableWhere() {
            switch ($this->dbName) {
                case "NL_USER":
                    return "(tbl.ID_NL_USER_PERMISSION != 2) AND (2 = " . $_SESSION["ID_NL_USER_PERMISSION"] . ")";
                    break;
                case "NL_PROP_RESALE":
                    if ($_SESSION["onlymy"] == "1") {
                        return "(tbl.ID_NL_USER = " . $_SESSION["ID_NL_USER"] . ")";
                    } else {
                        return "";
                    }
                    break;
                default:
                    return "";
                    break;
            }
        }

        public function getjqGridCustom() {
            switch ($this->dbName) {
                case "NL_PROP_RESALE":
                    $return = '
<script>
function showOnlyMy(jqgrid, row) {
    ';
                if ($_SESSION["ID_NL_USER_PERMISSION"] != "2") {
                    $return .= '
    if (row["ID_NL_USER"] != "' . $_SESSION["NL_USER_SHORT"] . '") {
        $("#edit_" + jqgrid + "_top, #del_" + jqgrid + "_top").hide();
    } else {
        $("#edit_" + jqgrid + "_top, #del_" + jqgrid + "_top").show();
    }
                    ';
                }
                    $return .= '
}
</script>
                    ';
                    return $return;
                    break;
                default:
                    return "";
                    break;
            }
        }

        public function renderTable() {
            $jqGridHtml = "";
            $jqGridHtml .= file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/admin/partial/jqgrid.html");
            if ($this->jqGridCustom) {
                $jqGridHtml .= "
                " . $this->jqGridCustom;
            }
            $colNames = '[';//""';
            $colModel = '[';//{ name: "ID_' . $this->dbName . '", index: "ID_' . $this->dbName . '", width : resizeData[pathName + "ID_' . $this->dbName . '"] ? resizeData[pathName + "ID_' . $this->dbName . '"] : 100}';
            $afterShowFormAdd = 'afterShowForm : function(formid) {
                initChosen($(formid.selector + " select:visible"));

                var $quill_elem = $(formid.selector + " .g-quill");
                if ($quill_elem.length > 0) {
                    
                    var toolbarOptions = [
                        ["bold", "italic", "underline", "strike"],        // toggled buttons
                        [{ "list": "ordered"}, { "list": "bullet" }],
                        [{ "script": "sub"}, { "script": "super" }],      // superscript/subscript
                        [{ "indent": "-1"}, { "indent": "+1" }],          // outdent/indent
                        [{ "header": [1, 2, 3, 4, 5, 6, false] }],
                        ["link", "image"],
                        [{ "color": [] }, { "background": [] }],          // dropdown with defaults from theme
                        [{ "align": [] }]
                    ];

                
                    var quill = new Quill($quill_elem[0], {
                        modules: {
                            toolbar: toolbarOptions
                        },
                        theme: "snow"
                    });
                
                    //console.log($quill_elem.data("value"));
                    if ($quill_elem.data("value")) {
                        //console.log(JSON.parse(decodeURIComponent($quill_elem.data("value"))));
                        quill.setContents(JSON.parse(decodeURIComponent($quill_elem.data("value"))));
                    }
                
                    $quill_elem.data("quill", quill);
                }

                modalResize();';

            $afterShowFormEdit = $afterShowFormAdd;

            switch ($this->dbName) {
                case "NL_PROP_RESALE":
                    $afterShowFormEdit .= '
                        if ($(formid.selector + " #ID_NL_USER").val() == ' . $_SESSION["ID_NL_USER"] . ') {
                            $(formid.selector + " #tr_' . $this->dbName . '_PHONE_OWNER").css("display", "");
                        } else {
                            $(formid.selector + " #tr_' . $this->dbName . '_PHONE_OWNER").css("display", "none");
                        }
                    ';
                    break;
            }

            for ($i = 0; $i <= (count($this->colArray) / 2); $i++) {
                /* @var $col ObjectParam */
                $col = $this->colArray[$i];
                if ($i > 0) {
                    $colNames .= ',';
                    $colModel .= ',';
                }
                $colNames .= '"' . $col->rusName . '"';
                // colModel - first params
                $colWidth = 120;
                if ($col->width) {
                    $colWidth = $col->width;
                }
                $colModel .= '{ name: "' . $col->dbName . '", index: "' . $col->dbName . '", width : resizeData[pathName + "' . $col->dbName . '"] ? resizeData[pathName + "' . $col->dbName . '"] : ' . $colWidth;
                // colModel - hidden
                if (!$col->render) {
                    $colModel .= ', hidden : true';
                }
                // colModel - editable
                if ($col->editable) {
                    $colModel .= ', editable : true';
                    // colModel - edittype
                    if ($col->type == "string") {
                        if ($col->maxLength > 50) {
                            $colModel .= ', edittype : "textarea"';
                        }
                    } elseif ($col->type == "select") {
                        $colModel .= ', edittype : "select"';
                    } elseif ($col->type == "checkbox") {
                        $colModel .= ', edittype : "checkbox"';
                    } elseif (($col->type == "file") || ($col->type == "map")) {
                        $colModel .= ', edittype : "text"';
                    } elseif (($col->type == "photo") || ($col->type == "photos")) {
                        $colModel .= ', edittype : "text", formatter: photosFormatter ';
                    } elseif ($col->type == "encrypted") {
                        $colModel .= ', edittype : "password"';
                    } elseif ($col->type == "rich") {
                        $colModel .= ', edittype : "custom"';
                    } elseif ($col->type == "float") {
                        $colModel .= ", formatter: numberFormatter ";
                    }
                    if (($col->formatter !== false) && ($col->type != "photo") && ($col->type != "photos")) {
                        $colModel .= ', formatter: function(cellValue, options, rowObject) {
                        ' . $col->formatter . '
                        }';
                    }
                    // colModel - edit options
                    $colModel .= ", editoptions : {";
                    $colModelEditOptions = "";
                    $dataInit = "dataInit: function(el) {";
                    if ($col->editHidden == false) {
                        $dataInit .= '
                            $(el).parent().parent().addClass("g-hidden");
                        ';
                    }
                    if ($col->type == "map") {
                        $dataInit .= '
                            $(el).addClass("g-mapInput");
                            $(el).parent().append("<span class=\'g-mapShowHide g-link\'>Показать/скрыть карту</span>");
                            $(el).next().click(function() {
                                $(this).next().slideToggle();
                            });
                            ymaps.ready(function () {
                                var idYMaps = el.id + "_ymaps";
                                $(el).parent().append("<div id=\'" + idYMaps + "\' class=\'g-mapYandex\'></div>");
                                var myMap = new ymaps.Map(idYMaps, {
                                    center: [44.891026, 37.32193],
                                    zoom: 14,
                                    controls: ["searchControl", "typeSelector", "fullscreenControl", "zoomControl"]
                                });
                                
                                var searchControl = myMap.controls.get("searchControl");
                                searchControl.events.add("change", function () {
                                    $(el).val(searchControl.getRequestString());
                                    //console.log("request: " + searchControl.getRequestString());
                                }, this);
                                
                                $(el).change(function() {
                                    if ($(el).val()) {
                                        searchControl.search($(el).val()).then(function () { searchControl.showResult(0); });
                                    }
                                });
                                
                                searchControl.options.set("fitMaxWidth", true).set("float", "left");
                                
                                if ($(el).val()) {
                                    searchControl.search($(el).val()).then(function () { searchControl.showResult(0); });
                                }
                                
                                $(".g-mapYandex").hide();
                            });
                        ';
                    } elseif ($col->type == "date") {
                        $dataInit .= '
                            $(el).datetimepicker({format: "Y-m-d",timepicker: false});
                        ';
                    } elseif ($col->type == "encrypted") {
                        $dataInit .= '
                            var rndBtn = $("<button class=\"g-btn\">случайный</button>");
                            rndBtn.click(function() {
                                $(el).attr("type", "text").val(Math.random().toString(36).slice(-8));
                            });
                            $(el).after(rndBtn);
                        ';
                    } elseif ($col->type == "select") {
                        if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        $colModelEditOptions .= 'value : "';

                        $colModelEditOptions .= ':не выбрано';

                        $selectTableName = str_replace("_PARENT", "", str_replace("ID_", "", $col->dbName));
                        $query = "SELECT * FROM " . $selectTableName;
                        $res = db_query($query) or die(db_error($query));
                        if (db_num_rows($res) > 0) {
                            while ($row = db_fetch_assoc($res)) {
                                $colModelEditOptions .= ";" . $row[str_replace("_PARENT", "", $col->dbName)] . ":" . mb_ereg_replace('"', '\\"', $row[$selectTableName . "_SHORT"]);
                            }
                        }
                        $colModelEditOptions .= '"';

                    } elseif ($col->type == "checkbox") {
                        if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        $colModelEditOptions .= 'value : "Да:Нет"';
                    } elseif (($col->type == "photo") || ($col->type == "photos") || ($col->type == "file")) {
                        /*if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        $colModelEditOptions .= 'src: "/img/empty.png?1", dataInit: function(el) {
                            var parent = $(el).parent();
                            var rowid = $(el).attr("rowid");
                            var name = $(el).attr("name);
                            $("el").remove();
                            parent.append(\'<input type="file" id="\' + name + \'" name="\' + name + \'" rowid="\' + rowid + \'" class="FormElement">\');
                        }';*/

                        $multiple = "false";
                        if ($col->type == "photos") {
                            $multiple = "true";
                        }

                        $only_photo = "true";
                        if ($col->type == "file") {
                            $only_photo = "false";
                        }

                        /*$colModelEditOptions .= 'defaultValue: "[\"\/img\/empty.png\"]", 'dataInit: function(el){
                            dataInitFileFunction(el, "' . $this->dbName . '", "' . $col->dbName . '", ' . $multiple . ');
                        }';*/

                        $dataInit .= '
                            var elVal = $.parseHTML($(el).val());
                            //console.log(elVal)
                            if (elVal) {
                                $(el).val(elVal[1].innerText);
                            }
                            dataInitFileFunction(el, "' . $this->dbName . '", "' . $col->dbName . '", ' . $multiple . ', ' . $only_photo . ');
                        ';
                    } elseif ($col->type == "rich") {
                        if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        $colModelEditOptions .= 'custom_element : get_quill_elem, custom_value : get_quill_value';
                    }
                    if ($col->defValue != false) {
                        if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        if ($col->type == "string") {
                            $colModelEditOptions .= 'defaultValue : "' . $col->defValue . '"';
                        } else {
                            $colModelEditOptions .= 'defaultValue : ' . $col->defValue;
                        }
                    }

                    $dataInit .= " }";
                    if ($colModelEditOptions != "") {
                        $colModelEditOptions .= ", ";
                    }
                    $colModelEditOptions .= $dataInit;

                    $colModel .= $colModelEditOptions . "}";
                    // colModel - edit rules
                    $colModel .= ", editrules : {";
                    $colModelEditRules = "";
                    if ($col->required) {
                        if ($colModelEditRules != "") {
                            $colModelEditRules .= ", ";
                        }
                        $colModelEditRules .= "required: true";
                    }
                    if (!$col->render) {
                        if ($colModelEditRules != "") {
                            $colModelEditRules .= ", ";
                        }
                        $colModelEditRules .= "edithidden: true";
                    }
                    if ($col->type == "float") {
                        if ($colModelEditRules != "") {
                            $colModelEditRules .= ", ";
                        }
                        $colModelEditRules .= "number: true";
                    } elseif ($col->type == "integer") {
                        if ($colModelEditRules != "") {
                            $colModelEditRules .= ", ";
                        }
                        $colModelEditRules .= "integer: true";
                    }
                    $colModel .= $colModelEditRules . "}";
                }
                // colModel - form options
                $colModel .= ", formoptions : {";
                $colModel .= 'label: "' . $col->rusName . '"';
                if ($col->required) {
                    $colModel .= ', elmsuffix: "(*)"';
                }
                $colModel .= "}";

                $colModel .= "}";

                if (($col->onInit !== false) || ($col->onChange !== false)) {
                    $asf = '
                        (function(){
                            var el = "#' . $col->dbName . '";
                    ';
                    $afterShowFormAdd .= $asf;
                    $afterShowFormEdit .= $asf;
                }
                if ($col->onInit !== false) {
                    $asf = '
                            ' . $col->onInit . '
                        ';
                    $afterShowFormAdd .= $asf;
                    $afterShowFormEdit .= $asf;
                }
                if ($col->onChange !== false) {
                    $asf = '
                            $(el).bind("change", function(){' . $col->onChange . ';});
                        ';
                    $afterShowFormAdd .= $asf;
                    $afterShowFormEdit .= $asf;
                }
                if (($col->onInit !== false) || ($col->onChange !== false)) {
                    $asf = '
                        }());
                    ';
                    $afterShowFormAdd .= $asf;
                    $afterShowFormEdit .= $asf;
                }
                if ($col->onAddInit !== false) {
                    $asf = '
                        (function(){
                            var el = "#' . $col->dbName . '";
                    ';
                    $afterShowFormAdd .= $asf;
                    $asf = '
                            ' . $col->onAddInit . '
                        ';
                    $afterShowFormAdd .= $asf;
                    $asf = '
                        }());
                    ';
                    $afterShowFormAdd .= $asf;
                }
                if ($col->onEditInit !== false) {
                    $asf = '
                        (function(){
                            var el = "#' . $col->dbName . '";
                    ';
                    $afterShowFormEdit .= $asf;
                    $asf = '
                            ' . $col->onEditInit . '
                        ';
                    $afterShowFormEdit .= $asf;
                    $asf = '
                        }());
                    ';
                    $afterShowFormEdit .= $asf;
                }
            }
            $colNames .= "]";
            $colModel .= "]";
            $jqGridHtml = mb_ereg_replace("{tableName}", $this->dbName, $jqGridHtml);
            $jqGridHtml = mb_ereg_replace("{colNames}", $colNames, $jqGridHtml);
            $jqGridHtml = mb_ereg_replace("{colModel}", $colModel, $jqGridHtml);

            $addDelForm = "";
            if ($this->readOnly) {
                $addDelForm = 'add : false, edit : false, del : false';
            } elseif (($this->editOnly) || ($_SESSION["ID_NL_USER_PERMISSION"] == "3")) {
                $addDelForm = 'add : false, edit : true, edittext : "Изменить", del : false';
            } else {
                $addDelForm = 'add : true, addtext : "Добавить", edit : true, edittext : "Изменить", del : true, deltext : "Удалить"';
            }
            $jqGridHtml = mb_ereg_replace("{addEditForm}", $addDelForm, $jqGridHtml);

            $asf = '
            }';
            $afterShowFormAdd .= $asf;
            $afterShowFormEdit .= $asf;
            $jqGridHtml = mb_ereg_replace("{afterShowFormAdd}", $afterShowFormAdd, $jqGridHtml);
            $jqGridHtml = mb_ereg_replace("{afterShowFormEdit}", $afterShowFormEdit, $jqGridHtml);

            echo $jqGridHtml;
        }

        public function getData($page = 0, $limit = 0, $sidx = "1", $sord = "ASC", $search_where = "(1 = 1)") {
            $data = Array();

            $leftJoin = $this->get_query_left_joins();

            //if (isset($this->where) && ($this->where != false) && (trim($this->where) != "")) {
                //$query = "SELECT " .
            //} else {
                $query = "SELECT *";
                for ($i = 1; $i <= (count($this->colArray)); $i++) {
                    /* @var $col ObjectParam */
                    $col = $this->colArray[$i];
                    if ($col->type == "encrypted") {
                        $query .= ", AES_DECRYPT(" . $col->dbName . ",'" . AESKEY . "') AS " . $col->dbName . "_DECRYPTED";
                    }
                }
                $query .= " FROM " . $this->dbName . " tbl " . $leftJoin . " WHERE " . $search_where;
                if (isset($this->where) && ($this->where != false) && (trim($this->where) != "")) {
                    $query .= " AND " . $this->where;
                }
                $query .= " ORDER BY " . $sidx . " " . $sord;
            //}
            $query .= " LIMIT " . $limit * ($page - 1) . ", " . $limit;

            $res = db_query($query) or die(db_error($query));
            $i = 0;
            if (db_num_rows($res) > 0) {
                //echo print_r($this->colArray);
                while ($row = db_fetch_assoc($res)) {
                    $data[$i] = Array();
                    //array_push($data[$i], $row["ID_" . $this->dbName]);
                    //array_push($data[$i], ($i + 1) + (($page * $limit) - $limit));
                    for ($j = 0; $j <= (count($this->colArray) / 2); $j++) {
                        $col = $this->colArray[$j];
                        $rowName = $this->colArray[$j]->dbName;
                        if ($col->type == "select") {
                            $rowName = mb_ereg_replace("ID_", "", $rowName) . "_SHORT";
                        } elseif ($col->type == "encrypted") {
                            ;
                            $rowName .= "_DECRYPTED";
                        }
                        //echo $row[$rowName] . "\n";
                        array_push($data[$i], $row[$rowName]);
                    }
                    $i++;
                }
            }

            return $data;
        }

        public function showData() {
            // Номер запришиваемой страницы
            if (!isset($_GET['page'])) {
                $page = 1;
            } else {
                $page = $_GET['page'];
            }
            // Количество запрашиваемых записей
            if (!isset($_GET['rows'])) {
                $limit = 50;
            } else {
                $limit = $_GET['rows'];
            }
            // Поле, по которому следует производить сортировку
            $sidx = $_GET['sidx'];
            // Направление сортировки
            if (!isset($_GET['sord'])) {
                $sord = "ASC";
            } else {
                $sord = $_GET['sord'];
            }
            // Выполним запрос, который вернет суммарное кол-во записей в таблице
            $search_where = "(1=1)";
            foreach ($_GET as $key => $value) {
                if (strpos($key, "NL_") !== false) {
                    $search_field = $key;
                    if (strpos($key, "ID_") === 0) {
                        $search_field = substr($key, 3) . "_SHORT";
                    }
                    if (strpos($key, "_from") !== false) {
                        $search_field = str_replace("_from", "", $search_field);
                        $search_value = "$search_field >= " . $value;
                    } elseif (strpos($key, "_to") !== false) {
                        $search_field = str_replace("_to", "", $search_field);
                        $search_value = "$search_field <= " . $value;
                    } else {
                        $search_value = "$search_field = $value";
                        if (strpos($key, "ID_") === 0) {
                            $search_value = "$search_field LIKE '%" . $value . "%'";
                        } else {
                            foreach ($this->colArray as $col) {
                                if ($col->dbName == $key) {
                                    if (($col->type == "string") || ($col->type == "rich") || ($col->type == "photo") || ($col->type == "photos") || ($col->type == "file") || ($col->type == "date") || ($col->type == "checkbox") || ($col->type == "map")) {
                                        $search_value = "$search_field LIKE '%" . $value . "%'";
                                    }
                                    break;
                                }
                            }
                        }
                    }

                    $search_where .= " AND ($search_value)";
                }
            }

            $leftJoin = $this->get_query_left_joins();
            $query = "SELECT COUNT(*) AS COUNT FROM " . $this->dbName . " tbl $leftJoin WHERE $search_where";
            $res = db_query($query) or die(db_error($query));
            $row = db_fetch_assoc($res);
            // Теперь эта переменная хранит кол-во записей в таблице
            $count = $row['COUNT'];
            // Рассчитаем сколько всего страниц займут данные в БД
            if ($count > 0 && $limit > 0) {
                $total_pages = ceil($count / $limit);
            } else {
                $total_pages = 0;
            }
            // Если по каким-то причинам клиент запросил
            if (($page > $total_pages) && ($total_pages != 0))
                $page = $total_pages;
            // Рассчитываем стартовое значение для LIMIT запроса
            $start = $limit * $page - $limit;
            // Зашита от отрицательного значения
            if ($start < 0)
                $start = 0;
            // Запрос выборки данных
            $data = $this->getData($page, $limit, $sidx, $sord, $search_where);

            // Начало xml разметки
            $s = "<?xml version='1.0' encoding='utf-8'?>";
            $s .= "<rows>";
            $s .= "<page>" . $page . "</page>";
            $s .= "<total>" . $total_pages . "</total>";
            $s .= "<records>" . $count . "</records>";
            // Строки данных для таблицы
            // Не забудьте обернуть текстовые данные в <![CDATA[]]>
            if (count($data) > 0) {
                for ($i = 0; $i < count($data); $i++) {
                    $s .= '<row id="' . $data[$i][0] . '">';
                    for ($j = 0; $j < count($data[$i]); $j++) {
                        $s .= '<cell><![CDATA[' . $data[$i][$j] . ']]></cell>';
                    }
                    $s .= "</row>";
                }
            }
            $s .= "</rows>";
            // Перед выводом не забывайте выставить header с типом контента и кодировкой
            header("Content-type: text/xml; charset=utf-8");

            return $s;
        }

        public function saveData($oper, $id, $post) {
            // "add" - insert, "edit" - update, "del" - delete

            if ($this->readOnly) {
                die();
            }

            // Если пользователь может только редактировать запись
            if ((($this->editOnly) || ($_SESSION["ID_NL_USER_PERMISSION"] == "3")) && (($oper == "add") || ($oper == "del"))) {
                die();
            }

            if ($oper != "add") {
                $query_cur = "SELECT * FROM " . $this->dbName . " WHERE ID_" . $this->dbName . " = " . $id;
                $res_cur = db_query($query_cur) or die(db_error($query_cur));
                $row_cur = db_fetch_assoc($res_cur);

                if ($_SESSION["ID_NL_USER_PERMISSION"] == "3") {
                    die();
                } elseif (($_SESSION["ID_NL_USER_PERMISSION"] == "1") && ($row_cur["ID_NL_USER"] != $_SESSION["ID_NL_USER"])) {
                    die();
                }
            }

            $user_ip = 'unknown';
            if (getenv('REMOTE_ADDR')) {
                $user_ip = getenv('REMOTE_ADDR');
            } elseif (getenv('HTTP_FORWARDED_FOR')) {
                $user_ip = getenv('HTTP_FORWARDED_FOR');
            } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
                $user_ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_X_COMING_FROM')) {
                $user_ip = getenv('HTTP_X_COMING_FROM');
            } elseif (getenv('HTTP_VIA')) {
                $user_ip = getenv('HTTP_VIA');
            } elseif (getenv('HTTP_XROXY_CONNECTION')) {
                $user_ip = getenv('HTTP_XROXY_CONNECTION');
            } elseif (getenv('HTTP_CLIENT_IP')) {
                $user_ip = getenv('HTTP_CLIENT_IP');
            } else {
                $user_ip = 'unknown';
            }
            if (15 < strlen($user_ip)) {
                $ar = split(', ', $user_ip);
                for ($i = sizeof($ar) - 1; $i > 0; $i--) {
                    if ($ar[$i] != '' and !preg_match('/[a-zA-Zа-яА-Я]/', $ar[$i])) {
                        $user_ip = $ar[$i];
                        break;
                    }
                    if ($i == sizeof($ar) - 1) {
                        $user_ip = 'unknown';
                    }
                }
            }
            if (preg_match('/[a-zA-Zа-яА-Я]/', $user_ip)) {
                $user_ip = 'unknown';
            }
            // log master
            $query_log = "INSERT INTO NL_LOG(NL_LOG_DATE, NL_LOG_TIME, NL_LOG_IP, NL_LOG_IUD, NL_LOG_TABLE_NAME, ID_NL_USER) VALUES('" . date("Y.m.d") . "', '" . date("H:i:s") . "', '" . $user_ip . "', '" . $oper . "', '" . $this->dbName . "', " . $_SESSION["ID_NL_USER"] . " )";
            db_query($query_log) or die(db_error($query_log));
            $query_log = "SELECT LAST_INSERT_ID() AS ID_LOG";
            $res_log = db_query($query_log) or die(db_error($query_log));
            $row_log = db_fetch_assoc($res_log);

            $fields = Array();
            $values = Array();
            for ($i = 0; $i < (count($this->colArray) / 2); $i++) {
                /* @var $col ObjectParam */
                $col = $this->colArray[$i];

                if (($col->editable) && ($col->thisTable)) {
                    array_push($fields, $col->dbName);

                    if ((!isset($post[$col->dbName])) || (trim($post[$col->dbName]) == "")) {
                        array_push($values, "NULL");
                    } elseif (($col->type == "string") || ($col->type == "rich") || ($col->type == "photo") || ($col->type == "photos") || ($col->type == "file") || ($col->type == "date") || ($col->type == "checkbox") || ($col->type == "map")) {
                        array_push($values, "'" . $post[$col->dbName] . "'");
                    } elseif ($col->type == "encrypted") {
                        array_push($values, "AES_ENCRYPT('" . $post[$col->dbName] . "','" . AESKEY . "')");
                    } else {
                        array_push($values, $post[$col->dbName]);
                    }

                    if ($col->type != "encrypted") {
                        $valold = "''";
                        if (db_num_rows($res_cur) > 0) {
                            $valold = "'" . $row_cur[$col->dbName] . "'";
                        }
                        $valnew = "''";
                        if ($oper != "del") {
                            $valnew = "'" . $post[$col->dbName] . "'";
                        }

                        $query_log_detail = "INSERT INTO NL_LOG_DETAIL(ID_NL_LOG, NL_LOG_DETAIL_OLD, NL_LOG_DETAIL_NEW, NL_LOG_DETAIL_FIELD) VALUES (" . $row_log["ID_LOG"] . ", " . $valold . "," . $valnew . ", '" . $col->dbName . "')";
                        db_query($query_log_detail) or die(db_error($query_log_detail));
                    }
                }

                // Удаляем лишние изображения
                if (($col->type == "photo") || ($col->type == "photos")) {
                    $trueId = $id;
                    if ((isset($values[0])) && (trim($values[0]) != "") && ($values[0] != "NULL")) {
                        $trueId = $values[0];
                    }
                    $imgsPath = $_SERVER["DOCUMENT_ROOT"] . "/img/objects/" . str_replace("NL_", "", $this->dbName) . "/";
                    foreach (glob($imgsPath . str_replace($this->dbName . "_", "", $col->dbName) . "_" . $trueId . "*.jpg") as $fullFileName) {
                        $fileName = mb_substr($fullFileName, mb_strrpos($fullFileName, "/") + 1);
                        $needDel = true;
                        if ($oper != "del") {
                            $photos = json_decode($post[$col->dbName]);
                            for ($ji = 0; $ji < count($photos); $ji++) {
                                $curPhoto = mb_substr($photos[$ji], mb_strrpos($photos[$ji], "/") + 1);
                                if ($curPhoto == $fileName) {
                                    $needDel = false;
                                }
                            }
                        }
                        if ($needDel) {
                            unlink($imgsPath . $fileName);
                        }
                    }
                }
            }
            //
            $query = "";
            $tableName = substr($this->dbName, 3);
            /** @var ObjectParam $idObject */
            $idObject = $this->getMainTableCol("ID_" . $this->dbName . "", $tableName);
            if ($oper == "add") {
                $query = "INSERT INTO " . $this->dbName . " (" . implode(",", $fields) . ") VALUES(" . implode(",", $values) . ")";
            } elseif ($oper == "edit") {
                $query = "UPDATE " . $this->dbName . " SET ";
                for ($i = 0; $i < count($fields); $i++) {
                    if ($i > 0) {
                        $query .= ",";
                    }
                    $query .= $fields[$i] . " = " . $values[$i];
                }
                $query .= " WHERE ID_" . $this->dbName . " = " . $id;
            } elseif ($oper == "del") {
                $query = "DELETE FROM " . $this->dbName . " WHERE ID_" . $this->dbName . " = " . $id;
            }
            echo $query;
            db_query($query) or die(db_error($query));
        }

        public function get_query_left_joins() {
            $leftJoin = "";
            for ($i = 0; $i < count($this->leftJoins); $i++) {
                $lj = $this->leftJoins[$i];
                $leftJoin .= " LEFT JOIN " . $lj . " ON " . $lj . ".ID_" . $lj . " = tbl.ID_" . $lj;
            }
            return $leftJoin;
        }

    }

    function user_auth($login, $pass) {
        $id_user = $_SESSION["ID_NL_USER"] ? $_SESSION["ID_NL_USER"] : -1;
        $query = "SELECT * FROM NL_USER au WHERE ((au.NL_USER_LOGIN = '" . $login . "') AND (au.NL_USER_PASSWORD = aes_encrypt('" . $pass . "','" . AESKEY . "')) OR (au.ID_NL_USER = " . $id_user . "))";
        //echo $query;
        $res = db_query($query) or die(db_error($query));
        if (db_num_rows($res) > 0) {
            $row = db_fetch_assoc($res);
            $_SESSION["ID_NL_USER"] = $row["ID_NL_USER"];
            $_SESSION["NL_USER_LOGIN"] = $row["NL_USER_LOGIN"];
            $_SESSION["NL_USER_SHORT"] = $row["NL_USER_SHORT"];
            $_SESSION["NL_USER_FULL"] = $row["NL_USER_FULL"];
            $_SESSION["NL_USER_PHONE"] = $row["NL_USER_PHONE"];
            $_SESSION["ID_NL_USER_PERMISSION"] = $row["ID_NL_USER_PERMISSION"];
            $_SESSION["onlymy"] = "0";
        }
    }

    function user_logout() {
        unset($_SESSION["ID_NL_USER"]);
        unset($_SESSION["NL_USER_LOGIN"]);
        unset($_SESSION["NL_USER_SHORT"]);
        unset($_SESSION["NL_USER_FULL"]);
        unset($_SESSION["ID_NL_USER_PERMISSION"]);
    }

    function includeAdminPartsByLvl($wrap_start = "", $wrap_end = "") {
        global $page;

        $main_file = $_SERVER["DOCUMENT_ROOT"] . "/admin/parts/" . $page . ".php";
        if (file_exists($main_file)) {
            if ($wrap_start != "") {
                echo $wrap_start;
            }
            include $main_file;
            if ($wrap_end != "") {
                echo $wrap_end;
            }
        } else {
            include $_SERVER["DOCUMENT_ROOT"] . "/admin/parts/main.php";
        }
    }