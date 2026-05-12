<?php
// Connect to database
$conn = new mysqli('localhost', 'root', '', 'webgis');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get data from database
$sql = "SELECT Ky_Hieu as 'Ký hiệu', Ten_Geosite AS 'Ten_geosite', Toa_Do_X AS 'Kinh_do', Toa_Do_Y AS 'Vi_do', 
Kieu_GeoSite AS 'Kiểu GeoSite', Mo_Ta AS 'Mô tả', Tieu_Chi AS 'Tiêu chí', Hinh_Anh AS 'Hình ảnh', Ma_Tinh AS 'Mã tỉnh' FROM dia_di_san";
$result = $conn->query($sql);
$points = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $points[] = $row;
    }
}
$conn->close();

// Read file GeoJSON Polyline
$geoJsonFile = 'Việt Nam (tỉnh thành) - 34.geojson';
$geoJsonData = file_get_contents($geoJsonFile);
if ($geoJsonData === false) {
    die("Can't read GeoJSON file");
}
$geoJsonData = json_decode($geoJsonData, true); // Convert the GEOJSON data to PHP array
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Hệ thống thông tin địa lý các điểm địa di sản ở Việt Nam</title>
    <link rel="stylesheet" href="https://js.arcgis.com/4.31/esri/themes/light/main.css" />
    <script src="https://js.arcgis.com/4.31/"></script>
    <style>
        /* Reset CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            color: #333;
        }

        /* Header */
        header {
            background-color: #0078d4;
            color: white;
            padding: 1rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Navigation Bar */
        nav {
            background-color: #333;
            padding: 0.5rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        nav button {
            background-color: #0078d4;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        nav button:hover {
            background-color: #005bb5;
        }

        /* Main Container */
        .container {
            display: flex;
            height: calc(100vh - 145px);
        }

        /* Sidebar */
        .sidebar {
            width: 300px;
            background-color: white;
            padding: 1rem;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar h2 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: #0078d4;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar ul li {
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .sidebar ul li:hover {
            background-color: #f0f0f0;
        }

        /* Map Container */
        #viewDiv {
            flex: 1;
            height: 100%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Footer */
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 0.5rem;
            font-size: 0.9rem;
            position: fixed;
            bottom: 0;
            width: 100%;
        }

        footer a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: bold;
        }

        footer a:hover {
            text-decoration: underline;
        }

        /* Popup Styling */
        .esri-popup__header {
            background-color: #0078d4;
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .esri-popup__content {
            font-size: 14px;
            color: #333;
        }

        .esri-popup__button {
            color: #0078d4;
        }

        .esri-popup__button:hover {
            color: #005bb5;
        }
    </style>
</head>
<body>
    <header>Hệ thống thông tin địa lý các điểm địa di sản ở Việt Nam</header>
    <nav>
        <button onclick="switchBasemap('streets-navigation-vector')">Streets</button>
        <button onclick="switchBasemap('satellite')">Satellite</button>
        <button onclick="switchBasemap('topo')">Topography</button>
        <button onclick="switchBasemap('osm')">Open Street Map</button>
    </nav>
    <div class="container">
        <div class="sidebar">
            <h2>Điểm địa di sản</h2>
            <ul id="stationList">
                <?php foreach ($points as $point): ?>
                    <li onclick="zoomToStation(<?= $point['Kinh_do'] ?>, <?= $point['Vi_do'] ?>)">
                        <?= $point['Ten_geosite'] ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div id="viewDiv"></div>
    </div>
    <footer>
        Developed by <a href="https://www.facebook.com/dy.ungou/" target="_blank">Ngô Viễn Hoàng Duy_21170078</a>. Powered by ArcGIS API for JavaScript.
    </footer>
    <script>
        require([
            "esri/Map",
            "esri/views/MapView",
            "esri/Graphic",
            "esri/layers/GraphicsLayer",
            "esri/layers/GeoJSONLayer",
            "esri/Basemap"
        ], (Map, MapView, Graphic, GraphicsLayer, GeoJSONLayer, Basemap) => {
            const map = new Map({
                basemap: "streets-navigation-vector"
            });

            const view = new MapView({
                container: "viewDiv",
                map: map,
                zoom: 5,
                center: [106.308, 16.6455] // Center in Vietnam
            });

            const graphicsLayer = new GraphicsLayer();
            map.add(graphicsLayer);

            // Point data from PHP
            const data = <?php echo json_encode($points); ?>;

            if (data.length > 0) {
                data.forEach((point, index) => {
                    const colorList = [
                    "#FF0000", "#00FF00", "#0000FF", "#FFFF00", "#FF00FF", 
                    "#00FFFF", "#F4C29D", "#40E0D0", "#6495ED", "#000080",
                    "#8A2BE2", "#A52A2A", "#5F9EA0", "#7FFF00", "#D2691E",
                    "#DC143C", "#FF8C00", "#FFD700", "#ADFF2F", "#32CD32",
                    "#8B008B", "#FF4500", "#2E8B57", "#4682B4", "#DA70D6"
                    ];
                    const color = colorList[index % colorList.length];

                    const pointGraphic = new Graphic({
                        geometry: {
                            type: "point",
                            longitude: parseFloat(point.Kinh_do),
                            latitude: parseFloat(point.Vi_do)
                        },
                        symbol: {
                            type: "simple-marker",
                            color: color,
                            size: "12px",
                            outline: {
                                color: "#000000",
                                width: 1
                            }
                        },
                        // BƯỚC 1: Lấy toàn bộ dữ liệu từ point đưa vào attributes
                        attributes: {
                            name: point.Ten_geosite,
                            kyHieu: point['Ký hiệu'],
                            kinhDo: point.Kinh_do,
                            viDo: point.Vi_do,
                            kieuGeosite: point['Kiểu GeoSite'],
                            moTa: point['Mô tả'],
                            tieuChi: point['Tiêu chí'],
                            hinhAnh: point['Hình ảnh'], // Đường dẫn ảnh từ CSDL
                            provinces: point['Mã tỉnh']
                        },
                        // BƯỚC 2: Thiết kế lại Popup (Dùng HTML và thẻ <img>)
                        popupTemplate: {
                            title: "{name} ({kyHieu})",
                            content: `
                                <div style="margin-bottom: 10px;">
                                    <b>Kinh độ:</b> {kinhDo} <br>
                                    <b>Vĩ độ:</b> {viDo} <br>
                                    <b>Tỉnh:</b> {provinces} <br>
                                    <b>Kiểu GeoSite:</b> {kieuGeosite} <br>
                                    <b>Tiêu chí:</b> {tieuChi} <br>
                                    <b>Mô tả:</b> {moTa}
                                </div>
                                <img src="{hinhAnh}" alt="{name}" style="width: 100%; max-width: 300px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                            `
                        }
                    });

                    graphicsLayer.add(pointGraphic);
                });
            }

            // Add GeoJSON Polyline layer into basemap
            const geoJsonData = <?php echo json_encode($geoJsonData); ?>;
            const geoJsonLayer = new GeoJSONLayer({
                url: "data:application/json;charset=utf-8," + encodeURIComponent(JSON.stringify(geoJsonData)),
                renderer: {
                    type: "simple",
                    symbol: {
                        type: "simple-line",
                        color: "red",
                        width: 0.5
                    }
                }
            });

            map.add(geoJsonLayer);

            // Basemap switching function
            window.switchBasemap = (basemapType) => {
                map.basemap = basemapType;
            };

            // Zoom to station function
            window.zoomToStation = (longitude, latitude) => {
                view.goTo({
                    center: [longitude, latitude],
                    zoom: 10
                });
            };
        });
    </script>
</body>
</html>