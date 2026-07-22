<?php
// ==============================================================================
// CONFIGURATION
// ==============================================================================
$storagePath = __DIR__ . '/tcx_export/';
// ==============================================================================

$message = '';
$messageClass = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sport'])) {
    
    try {
        // Sichere Ordner-Erstellung
        if (!is_dir($storagePath)) {
            if (!mkdir($storagePath, 0755, true)) {
                throw new Exception("Error: Export directory could not be created. Please check write permissions.");
            }
        }

        $sport = $_POST['sport'];
        $date = $_POST['date'];
        $time = $_POST['time'];
        $timezone = $_POST['timezone'] ?? 'Europe/Berlin';

        $distanceKm = floatval($_POST['distance']);
        $distanceM = round($distanceKm * 1000, 2);

        $hours = intval($_POST['hours']);
        $minutes = intval($_POST['minutes']);
        $seconds = intval($_POST['seconds']);

        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;

        $elevationGain = floatval($_POST['elevation']);

        if ($totalSeconds <= 0) {
            throw new Exception('Duration must be greater than zero.');
        }

        
        $startDateTime = new DateTime(
            $date . ' ' . $time,
            new DateTimeZone($timezone)
        );
        $startDateTime->setTimezone(new DateTimeZone('UTC'));
        $startISO = $startDateTime->format('Y-m-d\TH:i:s\Z');

        // ----------------------------------------------------------------------
        // Höhenprofil
        // ----------------------------------------------------------------------
        $startAltitude = 100.0;
        $endAltitude = $startAltitude + $elevationGain;
        $intervalSeconds = 5;
        $trackpoints = '';

        for ($s = 0; $s <= $totalSeconds; $s += $intervalSeconds) {
            $fraction = $s / $totalSeconds;
            
            $currentDistance = round($distanceM * $fraction, 2);
            $currentAltitude = round($startAltitude + (($endAltitude - $startAltitude) * $fraction), 1);

            $currentDateTime = clone $startDateTime;
            $currentDateTime->modify("+{$s} seconds");
            $currentISO = $currentDateTime->format('Y-m-d\TH:i:s\Z');

            $trackpoints .= "
          <Trackpoint>
            <Time>{$currentISO}</Time>
            <AltitudeMeters>{$currentAltitude}</AltitudeMeters>
            <DistanceMeters>{$currentDistance}</DistanceMeters>
          </Trackpoint>";
        }

        // Exakten Endpunkt ergänzen
        if (($totalSeconds % $intervalSeconds) !== 0) {
            $endDateTime = clone $startDateTime;
            $endDateTime->modify("+{$totalSeconds} seconds");
            $endISO = $endDateTime->format('Y-m-d\TH:i:s\Z');

            $trackpoints .= "
          <Trackpoint>
            <Time>{$endISO}</Time>
            <AltitudeMeters>{$endAltitude}</AltitudeMeters>
            <DistanceMeters>{$distanceM}</DistanceMeters>
          </Trackpoint>";
        }

        $avgSpeed = round($distanceM / $totalSeconds, 2);

        // ----------------------------------------------------------------------
        // TCX XML generieren
        // ----------------------------------------------------------------------
        $tcxString = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<TrainingCenterDatabase
    xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="
    http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2
    http://www.garmin.com/xmlschemas/TrainingCenterDatabasev2.xsd">
  <Activities>
    <Activity Sport="{$sport}">
      <Id>{$startISO}</Id>
      <Lap StartTime="{$startISO}">
        <TotalTimeSeconds>{$totalSeconds}</TotalTimeSeconds>
        <DistanceMeters>{$distanceM}</DistanceMeters>
        <MaximumSpeed>{$avgSpeed}</MaximumSpeed>
        <Calories>0</Calories>
        <Intensity>Active</Intensity>
        <TriggerMethod>Manual</TriggerMethod>
        <Track>
{$trackpoints}
        </Track>
      </Lap>
    </Activity>
  </Activities>
</TrainingCenterDatabase>
XML;

        $filename = $date . '_' . $sport . '_' . time() . '.tcx';
        $targetPath = rtrim($storagePath, '/') . '/' . $filename;

        if (file_put_contents($targetPath, $tcxString)) {
            $message = "Successfully saved: <strong>" . htmlspecialchars($filename) . "</strong>";
            $messageClass = "success";
        } else {
            throw new Exception("Error writing file. Does the container have write access to {$storagePath}?");
        }

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageClass = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCX Generator</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f9; color: #333; display: flex; justify-content: center; padding: 2rem; }
        .container { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h1 { font-size: 1.5rem; margin-top: 0; color: #fc4c02; text-align: center; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.4rem; }
        input[type="number"], input[type="date"], input[type="time"], select { width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1rem; }
        .time-inputs { display: flex; gap: 10px; }
        .time-inputs div { flex: 1; }
        button { width: 100%; background-color: #fc4c02; color: white; border: none; padding: 0.8rem; font-size: 1.1rem; font-weight: bold; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        button:hover { background-color: #e34402; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; text-align: center; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .note { font-size: 0.85rem; color: #666; margin-top: 1rem; text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <h1>TCX File Generator</h1>
    
    <?php if(!empty($message)): ?>
        <div class="alert <?php echo $messageClass; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="sport">Activity Type</label>
            <select name="sport" id="sport" required>
                <option value="Biking">Cycling (Standard)</option>
                <option value="GravelRide">Gravel Ride</option>
                <option value="MountainBikeRide">Mountain Biking</option>
                <option value="VirtualRide">Virtual Ride</option>
                <option value="EBikeRide">E-Bike Ride</option>
                <option value="Running">Running</option>
                <option value="Hike">Hiking / Trekking</option>
                <option value="Walk">Walking</option>
                <option value="Swimming">Swimming</option>
                <option value="Workout">Workout</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="form-group">
            <label for="timezone">Timezone</label>
            <select name="timezone" id="timezone" required>
                <option value="Europe/Berlin" selected>Europe/Berlin (CET/CEST)</option>
                <option value="America/Bogota">America/Bogota (COT)</option>
                <option value="UTC">UTC</option>
                <option value="America/New_York">America/New York (EST/EDT)</option>
                <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="date">Date</label>
            <input type="date" name="date" id="date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="form-group">
            <label for="time">Time (Start)</label>
            <input type="time" name="time" id="time" value="<?php echo date('H:i'); ?>" required>
        </div>

        <div class="form-group">
            <label for="distance">Distance (in kilometers)</label>
            <input type="number" name="distance" id="distance" step="0.01" min="0" placeholder="e.g., 25.5" required>
        </div>

        <div class="form-group">
            <label>Duration (Hours : Minutes : Seconds)</label>
            <div class="time-inputs">
                <div><input type="number" name="hours" min="0" placeholder="Hrs" value="1" required></div>
                <div><input type="number" name="minutes" min="0" max="59" placeholder="Min" value="15" required></div>
                <div><input type="number" name="seconds" min="0" max="59" placeholder="Sec" value="0" required></div>
            </div>
        </div>

        <div class="form-group">
            <label for="elevation">Elevation Gain (positive, in meters)</label>
            <input type="number" name="elevation" id="elevation" step="1" min="0" placeholder="e.g., 200" value="0" required>
        </div>

        <button type="submit">Save to Watch Folder</button>
        <p class="note">Target folder: <?php echo htmlspecialchars($storagePath); ?></p>
    </form>
</div>

</body>
</html>
