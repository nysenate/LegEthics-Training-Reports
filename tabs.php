<?php

function generate_tab_content($id, $text, $startdt, $enddt,
                              $active, $dtdisabled)
{
  $tabclass = $active ? 'active' : '';
  $dtclass = $dtdisabled ? 'uneditable-input' : 'datepicker';
  $disabledattr = $dtdisabled ? 'disabled' : '';
?>
      <div id="<?php echo $id;?>" class="tab-pane <?php echo $tabclass;?>">
        <form class="down" action="results.php" method="get">
        <fieldset>
          <legend>Generate a report for <?php echo $text;?></legend>
          <div class="clearfix">
            <label>Included agencies</label>
            <div class="input">
              <ul class="inputs-list">
                <li>
                  <label>
                  <input type="checkbox" checked name="Assembly" value="1">
                  <span>Assembly</span>
                  </label>
                </li>
                <li>
                  <label>
                  <input type="checkbox" checked name="Senate" value="1">
                  <span>Senate</span>
                  </label>
                </li>
                <li>
                  <label>
                  <input type="checkbox" checked name="LBDC" value="1">
                  <span>LBDC</span>
                  </label>
                </li>
              </ul>
            </div>
          </div><!-- /clearfix -->

          <div class="clearfix">
            <label>Date range</label>
            <div class="input">
              <div class="inline-inputs">
                <input type="text" name="start" value="<?php echo $startdt;?>" class="small <?php echo $dtclass;?>" <?php echo $disabledattr;?>>
                to
                <input type="text" name="end" value="<?php echo $enddt;?>" class="small <?php echo $dtclass;?>" <?php echo $disabledattr;?>>
              </div>
            </div>
          </div><!-- /clearfix -->

          <div class="actions">
            <input type="submit" value="Submit" class="btn primary">
          </div>
          <?php if ($dtdisabled) { ?>
          <input type="hidden" class="hidden" name="start" value="<?php echo $startdt;?>">
          <input type="hidden" class="hidden" name="end" value="<?php echo $enddt;?>">
          <?php } ?>
        </fieldset>
        </form>
      </div>
<?php
} // generate_tab_content()
?>
