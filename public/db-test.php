<?php
echo "<h1>Initiating Server Memory Wipe...</h1>";

// 1. Nuke the Server's OPcache (The real culprit)
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<h2 style='color:green;'>✅ OPcache RAM completely vaporized!</h2>";
} else {
    echo "<h2>⚠️ OPcache not enabled on this server.</h2>";
}

// 2. Nuke APCu Memory (Just in case)
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "<h2 style='color:green;'>✅ APCu Memory vaporized!</h2>";
}

echo "<h3>The server is officially forced to read the real .env file now. Go refresh the homepage!</h3>";
?>