//TODO: Remove comments

var startstop = false;
var refresh;
var keyName = "unknown";
var updatesPaused = false;
var lastStatusSuccess = Date.now();

console.log(config);

function loadCameras() {
   console.log("loading cameras");
   config.cameras.forEach(makeCamera);
}

function makeCamera(c) {
   console.log(c.title + " " + c.pos + c.img);
   var cam = config.template;
   cam = cam.replace("$img", c.img);
   cam = cam.replace(/\$title/g, c.title);
   cam = cam.replace(/\$pos/g, c.pos);
   $('#camctl').append(cam);
}

function clearStatusClasses() {
   $('#streamstate').removeClass("text-bg-success");
   $('#streamstate').removeClass("text-bg-info");
   $('#streamstate').removeClass("text-bg-danger");
   $('#streamstate').removeClass("text-bg-warning");
}

function getStatus() {
   if (startstop == true || updatesPaused == true) {
      return;
   }
   $.get("./status.php", function (data) {
      lastStatusSuccess = Date.now();
      clearStatusClasses();

      data = JSON.parse(data);

      if (data.streamingStatus == false) {
         $('#startbutton').prop('disabled', false);
         $('#stopbutton').prop('disabled', true);
         $('#streamstate').addClass("text-bg-info");
         $('#streamstate').html("Ready to Stream");
      }
      else if (data.streamingStatus == true) {
         $('#streamstate').addClass("text-bg-success");

         $('#streamstate').html("Streaming");
         $('#startbutton').prop('disabled', true);
         $('#stopbutton').prop('disabled', false);
      }
      else {
         console.log("Unknown status!");
         $('#streamstate').addClass("text-bg-danger");
         $('#streamstate').html("Error!");
      }

      if (data.currentScene == "Broadcast"){
         $('#sacrament').hide();
         $('#preview').show();
         $("#blank").hide();
      }else if (data.currentScene == "Sacrament") {
         $('#sacrament').show();
         $('#preview').hide();
         $("#blank").hide();
      } else if (data.currentScene == "Blank") {
         $('#sacrament').hide();
         $('#preview').hide();
         $("#blank").show();
      }

      $("#nightmodeswitch").prop("checked", data.nightMode == true);



   })
      .fail(function () {
         clearStatusClasses();
         console.log("Failed to load status!");
         $('#streamstate').addClass("text-bg-danger");
         $('#streamstate').html("Error!");
      });
}

var statusTimer = setInterval(getStatus, 3000);

function startStream() {
   startstop = true;
   $('#startbutton').prop('disabled', true);
   $('#stopbutton').prop('disabled', true);
   clearStatusClasses();
   $('#streamstate').addClass("btn-outline-danger");
   $('#streamstate').html("Starting Stream");
   
   $.post("/start.php", function (data) {
      $('#startbutton').prop('disabled', true);
      $('#stopbutton').prop('disabled', false);
   }
   ).fail(function(error){
      alert("Failed to start stream!");
   });
   startstop = false;
   startModal.hide();
}

function stopStream() {
   startstop = true;
   $('#startbutton').prop('disabled', true);
   $('#stopbutton').prop('disabled', true);
   clearStatusClasses();
   $('#streamstate').addClass("btn-outline-danger");
   $('#streamstate').html("Stopping Stream");
   $.post("/stop.php", function (data) {
      if (data.Result == "200") {
         $('#startbutton').prop('disabled', false);
         $('#stopbutton').prop('disabled', true);
      }
   });
   startstop = false;
   stopModal.hide();
}

function getPreview() {
   if (updatesPaused == true) {
      return;
   }
   d = new Date();
   $("#preview").attr("src", config.preview_uri + "?time=" + d.getTime());
}

function sacramentTime() {
   $.post("/set-sac.php", function (data) {
      if (data.Result == "200") {
         $("#sacrament").show();
         $("#preview").hide();
         $("#blank").hide();
      }
   });
}

function speakerTime() {
   $.post("/set-norm.php", function (data) {
      if (data.Result == "200") {
         $("#sacrament").hide();
         $("#preview").show();
         $("#blank").hide();
      }
   });
}

function blankTime() {
   $.post("/set-blank.php", function (data) {
      if (data.Result == "200") {
         $("#sacrament").hide();
         $("#preview").hide();
         $("#blank").show();
      }
   });
}

function refreshPTZ() {
   getPreview();
   clearInterval(refresh);
}

function PTZ(pos, el) {
   //"http://192.168.109.194/cgi-bin/ptzctrl.cgi?ptzcmd&poscall&0"
   //$.post(config.camera_uri + "/api/v1/ptzControl.lua?Action=load-preset&Id=" + pos, function (data) {
   $.post(config.camera_uri + pos, function (data) {

   });
   if (el) {
      var card = $(el).find('.card');
      card.addClass('ptz-flash');
      setTimeout(function () {
         card.removeClass('ptz-flash');
      }, 250);
   }
   refresh = setInterval(refreshPTZ, 1000);
   getPreview();
}

loadCameras();
getStatus();

const startModal = new bootstrap.Modal("#startModal", {
   keyboard: false
});

const stopModal = new bootstrap.Modal("#stopModal", {
   keyboard: false
});
const powerOffModal = new bootstrap.Modal("#powerOffModal", { keyboard: false });
const rebootModal = new bootstrap.Modal("#rebootModal", { keyboard: false });
const presetSaveModal = new bootstrap.Modal("#presetSaveModal", { keyboard: false });


getPreview();
var previewTimer = setInterval(getPreview, 1000);

function powerOff() {
   var input = $("#poweroffDayInput").val();
   var entered = parseInt(String(input).trim(), 10);
   var today = new Date().getDate();
   if (isNaN(entered) || entered !== today) {
      $("#poweroffDayError").show();
      return;
   }
   $("#poweroffDayError").hide();
   // Server independently re-validates this against its own local date --
   // this client-side check is just to avoid an unnecessary round trip for
   // obviously wrong input, it is not the real enforcement.
   $.post("/poweroff.php", { day: input }, function (data) {});
   powerOffModal.hide();
}

function rebootSystem() {
   var input = $("#rebootDayInput").val();
   var entered = parseInt(String(input).trim(), 10);
   var today = new Date().getDate();
   if (isNaN(entered) || entered !== today) {
      $("#rebootDayError").show();
      return;
   }
   $("#rebootDayError").hide();
   $.post("/reboot.php", { day: input }, function (data) {});
   rebootModal.hide();
}

function isValidPresetNumber(n) {
   return !isNaN(n) && Number.isInteger(n) && ((n >= 0 && n <= 89) || (n >= 100 && n <= 254));
}

function presetGoto() {
   var n = parseInt($("#presetNumberInput").val(), 10);
   if (!isValidPresetNumber(n)) {
      alert("Enter a valid preset number (0-89 or 100-254).");
      return false;
   }
   $.post("/ptz-goto.php", { pos: n }, function (data) {}).fail(function () {
      alert("Failed to move to preset " + n + ".");
   });
   return false;
}

function presetSavePrompt() {
   var n = parseInt($("#presetNumberInput").val(), 10);
   if (!isValidPresetNumber(n)) {
      alert("Enter a valid preset number (0-89 or 100-254).");
      return;
   }
   $("#presetSaveNumberDisplay").text(n);
   presetSaveModal.show();
}

function presetSave() {
   var n = parseInt($("#presetNumberInput").val(), 10);
   if (!isValidPresetNumber(n)) {
      presetSaveModal.hide();
      return;
   }
   $.post("/ptz-save.php", { pos: n }, function (data) {}).fail(function () {
      alert("Failed to save preset " + n + ".");
   });
   presetSaveModal.hide();
}

function ptzMove(dir) {
   var speed = $("#ptzspeed").val();
   $.get("/ptz-move.php?dir=" + dir + "&speed=" + speed);
}

function ptzZoom(dir) {
   var speed = $("#zoomspeed").val();
   $.get("/ptz-zoom.php?dir=" + dir + "&speed=" + speed);
}

function ptzNudge(dir) {
   var speed = $("#ptzspeed").val();
   $.get("/ptz-nudge.php?dir=" + dir + "&speed=" + speed);
}

function ptzZoomNudge(dir) {
   var speed = $("#zoomspeed").val();
   $.get("/ptz-zoom-nudge.php?dir=" + dir + "&speed=" + speed);
}

$("#ptzspeed").on("input", function () {
   $("#ptzspeedval").text($(this).val());
});

$("#zoomspeed").on("input", function () {
   $("#zoomspeedval").text($(this).val());
});

$("#ptzhomebutton").on("click", function () {
   ptzMove("home");
});

// Tap vs. press-and-hold: a short press (released before PTZ_TAP_THRESHOLD_MS
// elapses) sends a single server-timed nudge -- a deterministic pulse
// (move, wait proportional to speed, stop) so a tap always moves "one
// increment" regardless of exactly how long the click/touch itself lasted.
// A press held past the threshold instead starts continuous movement,
// stopped on release, same as before. Covers mouse and touch, plus losing
// the cursor off the button (mouseleave) so a drag-off doesn't leave the
// camera moving indefinitely.
var PTZ_TAP_THRESHOLD_MS = 200;

$(".ptz-btn").on("mousedown touchstart", function (e) {
   e.preventDefault();
   var el = $(this);
   var dir = el.data("dir");
   el.data("ptzPressActive", true);
   el.data("ptzHoldFired", false);
   var timer = setTimeout(function () {
      el.data("ptzHoldFired", true);
      ptzMove(dir);
   }, PTZ_TAP_THRESHOLD_MS);
   el.data("ptzHoldTimer", timer);
});
$(".ptz-btn").on("mouseup mouseleave touchend touchcancel", function (e) {
   e.preventDefault();
   var el = $(this);
   if (!el.data("ptzPressActive")) {
      return;
   }
   el.data("ptzPressActive", false);
   clearTimeout(el.data("ptzHoldTimer"));
   if (el.data("ptzHoldFired")) {
      ptzMove("stop");
   } else {
      ptzNudge(el.data("dir"));
   }
});

$(".ptz-zoom-btn").on("mousedown touchstart", function (e) {
   e.preventDefault();
   var el = $(this);
   var dir = el.data("zoomdir");
   el.data("ptzPressActive", true);
   el.data("ptzHoldFired", false);
   var timer = setTimeout(function () {
      el.data("ptzHoldFired", true);
      ptzZoom(dir);
   }, PTZ_TAP_THRESHOLD_MS);
   el.data("ptzHoldTimer", timer);
});
$(".ptz-zoom-btn").on("mouseup mouseleave touchend touchcancel", function (e) {
   e.preventDefault();
   var el = $(this);
   if (!el.data("ptzPressActive")) {
      return;
   }
   el.data("ptzPressActive", false);
   clearTimeout(el.data("ptzHoldTimer"));
   if (el.data("ptzHoldFired")) {
      ptzZoom("stop");
   } else {
      ptzZoomNudge(el.data("zoomdir"));
   }
});

// Safety net: if the page is closed/navigated away mid-press, try to stop
// any in-progress camera movement rather than leaving it drifting.
window.addEventListener("beforeunload", function () {
   navigator.sendBeacon("/ptz-move.php?dir=stop&speed=1");
   navigator.sendBeacon("/ptz-zoom.php?dir=stop");
});

$("#nightmodeswitch").on("change", function () {
   var enabled = $(this).is(":checked") ? "1" : "0";
   $.get("/night-mode.php?enable=" + enabled);
});

$("#pauseUpdatesSwitch").on("change", function () {
   updatesPaused = $(this).is(":checked");
   if (!updatesPaused) {
      // Resuming -- refresh right away instead of waiting for the next tick.
      getStatus();
      getPreview();
   }
});

// ---- Browser-side connection health check ----
// Shows a warning banner if getStatus() hasn't succeeded in ~10+ seconds
// (and updates aren't explicitly paused), then tries to figure out whether
// the problem is "your device" or "the broadcast computer" by testing
// general Internet reachability and, best-effort, comparing this device's
// locally-visible IP against the broadcast computer's own LAN IP/subnet.

var STALE_THRESHOLD_MS = 10000;
var REACHABILITY_CHECK_INTERVAL_MS = 5000;
var lastReachabilityCheck = 0;
var localIpsChecked = false;

function ipToInt(ip) {
   var parts = String(ip).split('.').map(Number);
   if (parts.length !== 4 || parts.some(function (n) { return isNaN(n) || n < 0 || n > 255; })) {
      return null;
   }
   return (((parts[0] << 24) | (parts[1] << 16) | (parts[2] << 8) | parts[3]) >>> 0);
}

function sameSubnet(ipA, ipB, netmask) {
   var a = ipToInt(ipA), b = ipToInt(ipB), m = ipToInt(netmask);
   if (a === null || b === null || m === null) {
      return null;
   }
   return (a & m) === (b & m);
}

// Best-effort local IP discovery via WebRTC ICE candidate gathering. Modern
// browsers increasingly obfuscate this behind mDNS ("xxxx.local") for
// privacy, in which case this simply finds nothing and callback([]) is
// called -- treat an empty result as "browser didn't expose it", not an
// error.
function discoverLocalIPs(callback) {
   var ips = [];
   var done = false;
   var pc;
   function finish() {
      if (done) {
         return;
      }
      done = true;
      try { pc.close(); } catch (e) {}
      callback(ips);
   }
   try {
      pc = new RTCPeerConnection({ iceServers: [] });
      pc.createDataChannel("");
      pc.onicecandidate = function (e) {
         if (!e || !e.candidate || !e.candidate.candidate) {
            finish();
            return;
         }
         var match = /([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})/.exec(e.candidate.candidate);
         if (match && ips.indexOf(match[1]) === -1) {
            ips.push(match[1]);
         }
      };
      pc.createOffer().then(function (offer) {
         return pc.setLocalDescription(offer);
      }).catch(finish);
      setTimeout(finish, 2000);
   } catch (e) {
      finish();
   }
}

function checkInternetReachable(callback) {
   if (typeof AbortController === "undefined" || typeof fetch === "undefined") {
      callback(null); // can't tell -- browser too old
      return;
   }
   var controller = new AbortController();
   var timeoutId = setTimeout(function () { controller.abort(); }, 4000);
   // generate_204 is a tiny, purpose-built connectivity-check endpoint --
   // no-cors mode means we never read the response body/status, only
   // whether the request completed at all vs. a network-level failure.
   fetch('https://www.gstatic.com/generate_204', { mode: 'no-cors', cache: 'no-store', signal: controller.signal })
      .then(function () {
         clearTimeout(timeoutId);
         callback(true);
      })
      .catch(function () {
         clearTimeout(timeoutId);
         callback(false);
      });
}

function updateConnectionWarning() {
   if (updatesPaused) {
      $("#connectionWarning").hide();
      return;
   }

   var staleMs = Date.now() - lastStatusSuccess;
   if (staleMs < STALE_THRESHOLD_MS) {
      $("#connectionWarning").hide();
      localIpsChecked = false; // reset so a future outage re-runs the IP check
      return;
   }

   $("#connectionWarningMain").text("Not receiving updates from broadcast computer.");
   $("#connectionWarning").show();

   var now = Date.now();
   if (now - lastReachabilityCheck < REACHABILITY_CHECK_INTERVAL_MS) {
      return; // throttle -- don't hammer the reachability check every tick
   }
   lastReachabilityCheck = now;

   checkInternetReachable(function (reachable) {
      var extra;
      if (reachable === true) {
         extra = "Your browser CAN reach the Internet. 1) Try refreshing the page. 2) Check that you're connected to the Liahona WiFi and clicked through the acceptance page. 3) Check YouTube to see if the broadcast is still working. 4) If you can reach YouTube but the broadcast isn't working, panic.";
      } else if (reachable === false) {
         extra = "Your web browser can't reach the Internet or the broadcast computer. Connect to the Liahona WiFi and accept the terms.";
      } else {
         extra = "";
      }
      $("#connectionWarningExtra").text(extra);

      // Best-effort "are you even on the right network" hint -- only
      // meaningful when the broadcast computer's own known address is on
      // the WiFi LAN (not e.g. a WireGuard tunnel address), and only if
      // the browser actually exposes a usable local IP at all.
      if (!localIpsChecked && typeof RTCPeerConnection !== "undefined" &&
          typeof SERVER_ADDR !== "undefined" && SERVER_ADDR.indexOf("192.168.") === 0) {
         localIpsChecked = true;
         discoverLocalIPs(function (ips) {
            if (ips.length === 0) {
               return; // browser didn't expose anything usable -- say nothing
            }
            var anySame = ips.some(function (ip) {
               return sameSubnet(ip, SERVER_ADDR, SERVER_NETMASK) === true;
            });
            if (!anySame) {
               $("#connectionWarningExtra").append(
                  '<div class="mt-2">This device does not appear to be on the same network as the broadcast computer (this device: ' +
                  ips.join(', ') + ').</div>'
               );
            }
         });
      }
   });
}

setInterval(updateConnectionWarning, 2000);
