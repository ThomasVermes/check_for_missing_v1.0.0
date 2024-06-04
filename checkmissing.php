<?php
/*
 *  Using getData or other methods will not work, since data analysed here are not saved yet
 */

namespace USBTOV\checkmissing;

use ExternalModules\AbstractExternalModule;

class checkmissing extends AbstractExternalModule
{
    public function redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        $this->launch_check($project_id, $instrument);
    }

    public function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        $this->launch_check($project_id, $instrument);
    }

   private function launch_check($project_id, $instrument)
    {
        if (!class_exists("ActionTagHelper")) include_once("classes/ActionTagHelper.php");
        $actionTags = ActionTagHelper::getActionTags("@CHECKMISSING", NULL, $instrument); // all the variables tagged with checkmissing for this instrument!
        $fields = array_keys($actionTags["@CHECKMISSING"]);
        /*echo "<br><br>" . json_encode($fields); //ie: ["gender","given_birth"]
      foreach ($fields as $field) {
         echo $actionTags["@CHECKMISSING"][$field]["params"] . "<br>";
      }
      */

        // Create an array to group the variables by the value of "params"
        $paramsGroup = [];

        // Iterate through the fields and group them by "params"
        foreach ($actionTags["@CHECKMISSING"] as $key => $value) {
            if (isset($value["params"])) {
                $paramValue = htmlspecialchars($value["params"], ENT_QUOTES, 'UTF-8');
                if (!isset($paramsGroup[$paramValue])) {
                    $paramsGroup[$paramValue] = [];
                }
                $paramsGroup[$paramValue][] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');

                /* Print the array of grouped parameters
                    echo "<br><br>Params Group: " . json_encode($paramsGroup);
                    Params Group: {
                            "warning1": [
                                "ethnicity",
                                "check1",
                                "pet"
                                ],
                            "warning2": [
                                "gender",
                                "given_birth",
                                "label1",
                                "label2",
                                ],
                            "warning3": [
                                "slidervar"
                                ],
                            "warning4": [
                                "checkbox1",
                                "checkbox2",
                                ]
                        }
         */
            }
        }
?>
        <script type='text/javascript'>
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Variable grouped by @CHECKMISSING parameter:');
                console.log(<?php echo json_encode($paramsGroup); ?>);

                function checkmissingvalues() {
                    // Iterate through the parameter group and execute the desired loop

                    <?php foreach ($paramsGroup as $paramValue => $fields) : ?> //go through all parameter
                        var rc_varname = document.querySelector('tr[sq_id="<?php echo $paramValue; ?>"]');
                        if (!rc_varname) {
                            console.warn("EM CHECKMISSING: Variable '<?php echo $paramValue; ?>' not found in this page. The @CHECKMISSING parameter ('<?php echo $paramValue; ?>') should be a variable available in this instrument. If you are on a multi-page survey the variable should be available in the current section");
                            console.warn("EM CHECKMISSING: All other parameters found in this page will work");
                        } else {
                            var paramValue_complete = true; // if this value is true through the whole loop the warning field will be hidden
                            <?php foreach ($fields as $field) : ?> //go through all variables with parameter defined above
                                jQuery('tbody tr[sq_id="' + '<?php echo $field; ?>' + '"]').each(function() {
                                    // for each row (tr) we check now if there is at least one entry in there that has been set
                                    var noMissing = false;
                                    //All kind of fields
                                    var inputs = jQuery(this).find('input, select, textarea');
                                    inputs.each(function() {
                                        if ((this.type == "radio" || this.type == "checkbox") && this.checked ||
                                            //Dropdown fields (are not input type)
                                            // The value "select-one" is a standard DOM characteristic for a <select> element that allows a single selection.
                                            //If the <select> element had the multiple attribute, then the type would be "select-multiple".
                                            //slider belong also here since it the value will displayed (hidden or not) in an type text input.
                                            (this.type == "text" || this.type == "textarea" || this.type == "select-one") && this.value !== "" ||
                                            jQuery(this).attr('disabled')) { // @READONLY fields are excluded.
                                            noMissing = true;
                                        }
                                    });


                                    //if for each variables with the same @CHECKMISSING parameter there aren't missing value set paramValue_complete = true;
                                    if (noMissing && paramValue_complete != false) {
                                        paramValue_complete = true;
                                    } else {
                                        paramValue_complete = false;
                                        noMissing = false;
                                    }
                                });
                            <?php endforeach; ?> //loop through varnames

                            if (paramValue_complete) {
                                rc_varname.classList.add("@HIDDEN");
                            } else {
                                rc_varname.classList.remove("@HIDDEN");
                            }
                        }
                    <?php endforeach; ?> // loop along @CHECKMISSING parameters
                }

                checkmissingvalues(); // initial call to verify the initial status

                // Add event listeners for the required events
                document.body.addEventListener('click', handleEvent);
                document.body.addEventListener('change', function(event) {
                    if (event.target.type === 'checkbox') {
                        handleEvent(event);
                    }
                });
                document.body.addEventListener('keydown', handleEvent);

                function handleEvent(event) {
                    setTimeout(checkmissingvalues, 100); // let the reset-button run before this function
                }
            });
        </script>
<?php
    }
}
