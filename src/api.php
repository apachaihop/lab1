<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connection.php';
include '../includes/header.php';

// OpenWeatherMap API key (you need to sign up for a free API key)
$api_key = 'YOUR_API_KEY_HERE';

// City for which we want to fetch weather data
$city = 'London';

// API endpoint
$api_url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$api_key}&units=metric";

// Function to get weather data from API
function getWeatherData($api_url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        die('Curl error: ' . curl_error($ch));
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code != 200) {
        die("API request failed with HTTP code $http_code");
    }
    return json_decode($response, true);
}

// Get weather data from API
$weather_info = getWeatherData($api_url);

// Debugging information
echo "Script executed by: " . exec('whoami') . "<br>";
echo "Script path: " . __FILE__ . "<br>";
echo "Server software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "PHP version: " . phpversion() . "<br>";

// Connect to the database
try {
    $conn = getConnection();

    // Check if we have recent data in the database
    $stmt = $conn->prepare("SELECT * FROM WeatherData WHERE city = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("s", $city);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Use data from database
        $row = $result->fetch_assoc();
        $temperature = $row['temperature'];
        $description = $row['description'];
        $humidity = $row['humidity'];
        $wind_speed = $row['wind_speed'];
        echo "Using cached data from database.<br>";
    } else {
        // Use data from API and insert into database
        $temperature = $weather_info['main']['temp'];
        $description = $weather_info['weather'][0]['description'];
        $humidity = $weather_info['main']['humidity'];
        $wind_speed = $weather_info['wind']['speed'];

        $stmt = $conn->prepare("INSERT INTO WeatherData (city, temperature, description, humidity, wind_speed) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsdd", $city, $temperature, $description, $humidity, $wind_speed);
        $stmt->execute();
        echo "Inserted new data into database.<br>";
    }

    $success_message = "Weather data for {$city} has been retrieved.";

    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
} finally {
    closeConnection($conn);
}
?>

<h1>Weather API Data</h1>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<h2>Current Weather in <?= htmlspecialchars($city) ?></h2>
<ul>
    <li>Temperature: <?= htmlspecialchars($temperature) ?>°C</li>
    <li>Description: <?= htmlspecialchars($description) ?></li>
    <li>Humidity: <?= htmlspecialchars($humidity) ?>%</li>
    <li>Wind Speed: <?= htmlspecialchars($wind_speed) ?> m/s</li>
</ul>

<?php include '../includes/footer.php'; ?>