var config =
{
   "camera_uri": "/ptz.php?cam=",
   "preview_uri": "/cam.jpg",
   "cameras": [
      { "title": "Speaker", "pos": 0, "img": "camera-placeholder.png" },
      { "title": "Preset 61", "pos": 61, "img": "camera-placeholder.png" },
      { "title": "Preset 62", "pos": 62, "img": "camera-placeholder.png" }
   ],
   "template": "<a onclick='PTZ($pos, this);'><div class='col'><div class='card shadow-sm ptz-card'><img src='/$img' class='preview preview-cam' alt='...'><div class='card-body'><p class='card-text'>$title</p><span class='preset-num'>$pos</span></div></div></div></a>"
};
