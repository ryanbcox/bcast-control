<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>Broadcast Controller</title>
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
      integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
   <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
   <link rel="stylesheet" href="styles.css?t=<?php echo filemtime(__DIR__ . '/styles.css'); ?>">
   <script src="config.js?t=<?php echo filemtime(__DIR__ . '/config.js'); ?>"></script>
   
</head>

<body>
   <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container">
         <a class="navbar-brand" href="#">Controller</a>

         <div class="d-flex">
            <span id="streamstate" class="badge text-bg-secondary">Unknown Status</span>
         </div>
      </div>
   </nav>

   <div id="connectionWarning" class="alert alert-danger m-3" style="display:none;" role="alert">
      <div id="connectionWarningMain">Not receiving updates from broadcast computer.</div>
      <div id="connectionWarningExtra" class="mt-2"></div>
   </div>

   <div class="modal fade" id="startModal" tabindex="-1">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title text-Success">Start Stream</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
               <p>Start streaming to YouTube?</p>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-success" onclick="startStream();">Start Stream</button>
            </div>
         </div>
      </div>
   </div>

   <div class="modal fade" id="stopModal" tabindex="-1">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title text-danger">Stop Stream</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
               <p>Once stopped, the stream cannot be restarted!</p>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-danger" onclick="stopStream();">Stop Stream</button>
            </div>
         </div>
      </div>
   </div>

   <div class="container my-3">
      <div class="card">
         <div class="card-header">
            Controls
         </div>
         <div class="card-body">
            <div class="btn-toolbar" role="toolbar">
               <div class="btn-group me-1" role="group">
                  <button id="startbutton" type="button" data-bs-toggle="modal" data-bs-target="#startModal" class="btn btn-success" disabled>Start</button>
               </div>
               <div class="btn-group me-1" role="group">
                  <button id="stopbutton" type="button" data-bs-toggle="modal" data-bs-target="#stopModal" class="btn btn-danger" disabled>Stop</button>
               </div>
               <div class="btn-group me-1" role="group">
                  <button id="spkrbutton" onclick="speakerTime();" type="button" class="btn btn-info">Normal</button>
               </div>
               <div class="btn-group me-1" role="group">
                  <button id="sacbutton" onclick="sacramentTime();" type="button" class="btn btn-warning">Sacrament</button>
               </div>
               <div class="btn-group me-1" role="group">
                  <button id="blankbutton" onclick="blankTime();" type="button" class="btn btn-secondary">Blank</button>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div class="container my-3">
      <div class="card">
         <div class="card-header">
            Video Preview
         </div>
         <div class="card-body">
            <img id="blank" src="Blank.jpg" class="preview border rounded" style="display:none;" alt="...">
            <img id="sacrament" src="SacramentTime.jpg" class="preview border rounded" style="display:none;" alt="...">
            <img id="preview" src="camera-placeholder.png" class="preview border rounded" alt="..." style="display:none;" >
         </div>
      </div>
   </div>

   <div class="container my-3">
      <div class="card">
         <div class="card-header">
            <button class="btn btn-link text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#ptzCollapse" aria-expanded="false" aria-controls="ptzCollapse">
               Manual PTZ Control
            </button>
         </div>
         <div class="collapse" id="ptzCollapse">
            <div class="card-body">
               <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch" id="nightmodeswitch">
                  <label class="form-check-label" for="nightmodeswitch">Night Test Mode (significantly brightened image -- does not change camera settings)</label>
               </div>
               <div class="row g-4">
                  <div class="col-auto text-center">
                     <div class="mb-2">Pan / Tilt</div>
                     <table class="ptz-pad">
                        <tr>
                           <td><button type="button" class="btn btn-outline-light ptz-btn" data-dir="leftup">&#8598;</button></td>
                           <td><button type="button" class="btn btn-outline-light ptz-btn" data-dir="up">&#8593;</button></td>
                           <td><button type="button" class="btn btn-outline-light ptz-btn" data-dir="rightup">&#8599;</button></td>
                        </tr>
                        <tr>
                           <td><button type="button" class="btn btn-outline-light ptz-btn" data-dir="left">&#8592;</button></td>
                           <td><button type="button" class="btn btn-secondary" id="ptzhomebutton">Home</button></td>
                           <td><button type="button" class="btn btn-outline-light ptz-btn" data-dir="right">&#8594;</button></td>
                        </tr>
                        <tr>
                           <td><button type="button" class="btn btn-outline-light ptz-btn" data-dir="leftdown">&#8601;</button></td>
                           <td><button type="button" class="btn btn-outline-light ptz-btn" data-dir="down">&#8595;</button></td>
                           <td><button type="button" class="btn btn-outline-light ptz-btn" data-dir="rightdown">&#8600;</button></td>
                        </tr>
                     </table>
                     <div class="mt-2">
                        <label for="ptzspeed" class="form-label">Speed: <span id="ptzspeedval">15</span></label>
                        <input type="range" class="form-range" id="ptzspeed" min="1" max="24" value="15">
                     </div>
                  </div>
                  <div class="col-auto text-center">
                     <div class="mb-2">Zoom</div>
                     <div class="btn-group-vertical">
                        <button type="button" class="btn btn-outline-light ptz-zoom-btn" data-zoomdir="in">Zoom In (+)</button>
                        <button type="button" class="btn btn-outline-light ptz-zoom-btn" data-zoomdir="out">Zoom Out (&minus;)</button>
                     </div>
                     <div class="mt-2">
                        <label for="zoomspeed" class="form-label">Speed: <span id="zoomspeedval">3</span></label>
                        <input type="range" class="form-range" id="zoomspeed" min="1" max="8" value="3">
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div class="container my-3">
      <div class="card">
         <div class="card-body">
            <div class="form-check form-switch">
               <input class="form-check-input" type="checkbox" role="switch" id="pauseUpdatesSwitch">
               <label class="form-check-label" for="pauseUpdatesSwitch">Pause all page updates</label>
            </div>
         </div>
      </div>
   </div>

   <div class="container my-3">
      <div class="card">
         <div class="card-header">
            Camera Selection
         </div>
         <div class="card-body">
            <div class="album py-1 bg-body-tertiary">
               <div class="container">
                  <div id="camctl" class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                  </div>
               </div>   
            </div>
         </div>
      </div>
   </div>

   <div class="container my-3">
      <div class="card">
         <div class="card-header">
            Preset Direct Control
         </div>
         <div class="card-body">
            <form id="presetGotoForm" onsubmit="return presetGoto();">
               <div class="input-group">
                  <input type="number" class="form-control" id="presetNumberInput" placeholder="Preset number" min="0" max="254">
                  <button class="btn btn-primary" type="submit">Go</button>
                  <button class="btn btn-outline-danger" type="button" id="presetSaveButton" onclick="presetSavePrompt();">Save</button>
               </div>
            </form>
         </div>
      </div>
   </div>

   <div class="modal fade" id="presetSaveModal" tabindex="-1">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title text-danger">Save Preset</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
               <p>This will overwrite whatever the camera currently has stored at preset <strong id="presetSaveNumberDisplay"></strong> with its current position. This cannot be undone. Are you sure?</p>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-danger" onclick="presetSave();">Save Preset</button>
            </div>
         </div>
      </div>
   </div>

   <div class="container my-3">
      <div class="card">
         <div class="card-header">
            Power
         </div>
         <div class="card-body">
            <div class="btn-toolbar" role="toolbar">
               <div class="btn-group me-1" role="group">
                  <button id="poweroffbutton" type="button" data-bs-toggle="modal" data-bs-target="#powerOffModal" class="btn btn-outline-danger">Power Off Broadcast Computer</button>
               </div>
               <div class="btn-group me-1" role="group">
                  <button id="rebootbutton" type="button" data-bs-toggle="modal" data-bs-target="#rebootModal" class="btn btn-outline-warning">Reboot Broadcast Computer</button>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div class="modal fade" id="powerOffModal" tabindex="-1">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title text-danger">Power Off Broadcast Computer</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
               <p>This will power off the broadcast computer immediately. It cannot be turned back on remotely -- someone must press the physical power button.</p>
               <p class="text-danger">DANGER: You requested to power off the broadcast computer. Confirm by inputting the day of the month (e.g. 9 for January 9).</p>
               <input type="text" class="form-control" id="poweroffDayInput" placeholder="Day of month" inputmode="numeric" autocomplete="off">
               <div id="poweroffDayError" class="text-danger mt-2" style="display:none;">That's not today's date -- power off cancelled.</div>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-danger" onclick="powerOff();">Power Off</button>
            </div>
         </div>
      </div>
   </div>

   <div class="modal fade" id="rebootModal" tabindex="-1">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title text-warning">Reboot Broadcast Computer</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
               <p>This will reboot the broadcast computer, interrupting the stream.</p>
               <p class="text-danger">DANGER: You requested to reboot the broadcast computer. Confirm by inputting the day of the month (e.g. 9 for January 9).</p>
               <input type="text" class="form-control" id="rebootDayInput" placeholder="Day of month" inputmode="numeric" autocomplete="off">
               <div id="rebootDayError" class="text-danger mt-2" style="display:none;">That's not today's date -- reboot cancelled.</div>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-warning" onclick="rebootSystem();">Reboot</button>
            </div>
         </div>
      </div>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq"
      crossorigin="anonymous"></script>
      <script>
         // Used by bcast.js's best-effort "are you on the right network"
         // check. SERVER_ADDR is whatever IP this specific request reached
         // the server on (LAN IP for WiFi clients, WireGuard IP for VPN
         // clients -- the subnet check only applies for the former).
         // SERVER_NETMASK is this network's actual netmask (confirmed via
         // the broadcast camera's own network config: 255.255.252.0, a /22).
         var SERVER_ADDR = "<?php echo addslashes($_SERVER['SERVER_ADDR'] ?? ''); ?>";
         var SERVER_NETMASK = "255.255.252.0";
      </script>
      <script src="bcast.js?t=<?php echo filemtime(__DIR__ . '/bcast.js'); ?>"></script>
</body>

</html>
