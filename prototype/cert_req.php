<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

/* ---------- DATABASE CONNECTION ---------- */

$host = "localhost";
$dbname = "parish_db";
$user = "postgres";
$password = "123456";

try {
    $pdo = new PDO(
        "pgsql:host=$host;dbname=$dbname",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed.");
}

/* ---------- GET CURRENT DATE ---------- */
$currentDate = date("Y-m-d");

/* ---------- SEARCH PROCESS ---------- */

$results = [];
$searchPerformed = false;
$currentType = "";
$currentName = "";
$currentDate = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" || isset($_GET['ajax'])) {
    $type = $_POST["cert_type"] ?? $_GET["cert_type"] ?? "";
    $name = $_POST["name"] ?? $_GET["name"] ?? "";
    $date = $_POST["date"] ?? $_GET["date"] ?? "";
    
    $currentType = $type;
    $currentName = $name;
    $currentDate = $date;

    if (!empty($type) && !empty($name)) {
        $searchPerformed = true;
        
        $sql = "
            SELECT *
            FROM records
            WHERE record_type = :type
            AND full_name ILIKE :name
        ";

        if (!empty($date)) {
            $sql .= " AND record_date = :date";
        }

        $sql .= " ORDER BY record_date DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(":type", $type);
        $stmt->bindValue(":name", "%$name%");

        if (!empty($date)) {
            $stmt->bindValue(":date", $date);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If this is an AJAX request, return JSON
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['results' => $results]);
            exit;
        }
    }
}

// Handle certificate request submission via AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_request'])) {
    $recordId = intval($_POST['record_id'] ?? 0);
    $requesterId = $_SESSION['user_id'];
    
    $response = ['success' => false, 'message' => ''];

    // Get record info
    $rstmt = $pdo->prepare("SELECT id, full_name, record_type FROM records WHERE id = :id");
    $rstmt->execute([':id' => $recordId]);
    $rec = $rstmt->fetch(PDO::FETCH_ASSOC);

    if ($rec) {
        $ins = $pdo->prepare(
            "INSERT INTO certificate_requests
                (requester_id, record_id, full_name, cert_type, purpose, status, requested_at)
             VALUES
                (:requester_id, :record_id, :full_name, :cert_type, '', 'pending', now())"
        );

        $ins->execute([
            ':requester_id' => $requesterId,
            ':record_id' => $rec['id'],
            ':full_name' => $rec['full_name'],
            ':cert_type' => $rec['record_type']
        ]);

        $response['success'] = true;
        $response['message'] = 'Certificate request submitted successfully.';
    } else {
        $response['message'] = 'Record not found.';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #printableDocument, #printableDocument * {
                visibility: visible;
            }
            #printableDocument {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
            }
            .no-print {
                display: none !important;
            }
        }
        .certificate-border {
            border: 2px solid #d4af37;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">

<!-- SIDEBAR -->
<aside class="fixed top-0 left-0 h-screen w-64 bg-white border-r flex flex-col p-6 sidebar no-print">

    <div class="flex items-center mb-8">
        <div class="bg-blue-600 text-white rounded-lg p-2 mr-3">⛪</div>
        <div>
            <h6 class="font-bold">Parish Records</h6>
            <small class="text-gray-500 text-xs">Management System</small>
        </div>
    </div>

    <?php
    $navItems = [
        "home.php" => "Dashboard",
        "record.php" => "Records",
        "new_record.php" => "New Record",
        "cert_req.php" => "Certificate Request",
        "cert_hist.php" => "Certificate History"
    ];
    ?>

    <nav class="flex flex-col space-y-1 flex-1">
        <?php foreach ($navItems as $file => $label): ?>
            <?php $active = ($currentPage === $file); ?>

            <a
                href="<?= $file ?>"
                class="px-4 py-2 rounded-lg transition <?= $active
                    ? "bg-blue-50 text-blue-600 font-semibold"
                    : "text-gray-600 hover:bg-blue-50 hover:text-blue-600" ?>"
            >
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="border-t pt-4">
        <div class="flex items-center mb-3">
            <div class="bg-gray-200 rounded-full p-2 mr-3">👤</div>
            <div>
                <div class="text-sm font-bold">Parish Secretary</div>
                <div class="text-xs text-gray-500">Secretary</div>
            </div>
        </div>

        <a
            href="login.php"
            class="block text-center border border-red-500 text-red-500 rounded-md py-1 text-sm hover:bg-red-50"
        >
            Sign Out
        </a>
    </div>

</aside>

<!-- MAIN CONTENT -->
<main class="ml-64 p-8">

    <div id="messageContainer" class="no-print"></div>

    <!-- SEARCH FILTERS -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 no-print">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Search Records</h3>
            <div id="loadingIndicator" class="hidden">
                <div class="flex items-center">
                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                    <span class="ml-2 text-sm text-gray-600">Searching...</span>
                </div>
            </div>
        </div>

        <form id="searchForm" method="POST" class="flex items-end gap-3">
            <!-- Certificate Type -->
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Certificate Type <span class="text-red-500">*</span>
                </label>
                <select
                    name="cert_type"
                    id="cert_type"
                    required
                    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-10"
                >
                    <option value="">Select type</option>
                    <option value="Baptism" <?= $currentType == 'Baptism' ? 'selected' : '' ?>>Baptism</option>
                    <option value="Confirmation" <?= $currentType == 'Confirmation' ? 'selected' : '' ?>>Confirmation</option>
                    <option value="Marriage" <?= $currentType == 'Marriage' ? 'selected' : '' ?>>Marriage</option>
                    <option value="Funeral" <?= $currentType == 'Funeral' ? 'selected' : '' ?>>Funeral</option>
                </select>
            </div>

            <!-- Name -->
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Name <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="<?= htmlspecialchars($currentName) ?>"
                    required
                    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-10"
                    placeholder="Enter full name"
                >
            </div>

            <!-- Date -->
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Date of Sacrament
                </label>
                <input
                    type="date"
                    name="date"
                    id="date"
                    value="<?= $currentDate ?: date('Y-m-d') ?>"
                    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-10"
                >
            </div>

            <!-- Search Button -->
            <div class="flex-none">
                <button
                    type="submit"
                    class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition duration-200 h-10 text-sm font-medium flex items-center justify-center"
                >
                    Search
                </button>
            </div>
        </form>
    </div>

    <!-- SEARCH RESULTS -->
    <div id="resultsContainer" class="<?= $searchPerformed ? '' : 'hidden' ?>">
        <div class="bg-white rounded-xl shadow-sm p-6 no-print">
            <h4 class="font-semibold text-lg mb-4">Matching Records</h4>

            <div id="recordsList" class="space-y-3">
                <?php if (!empty($results)): ?>
                    <?php foreach ($results as $row): ?>
                        <div class="border rounded-lg p-4 hover:shadow-md transition duration-200">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                            <?= htmlspecialchars($row["record_type"]) ?>
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            ID: <?= $row["id"] ?>
                                        </span>
                                    </div>
                                    
                                    <h5 class="font-semibold text-lg">
                                        <?= htmlspecialchars($row["full_name"]) ?>
                                    </h5>
                                    
                                    <p class="text-sm text-gray-600 mb-2">
                                        <span class="font-medium">Date:</span> 
                                        <?= date("F d, Y", strtotime($row["record_date"])) ?>
                                    </p>

                                    <?php if (!empty($row["details"])): ?>
                                        <?php $details = json_decode($row["details"], true); ?>
                                        <div class="text-sm text-gray-600 mt-2">
                                            <?php if (!empty($details['birth_place'])): ?>
                                                <p><span class="font-medium">Birth Place:</span> <?= htmlspecialchars($details['birth_place']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($details['parents'])): ?>
                                                <p><span class="font-medium">Parents:</span> <?= htmlspecialchars($details['parents']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($details['spouse'])): ?>
                                                <p><span class="font-medium">Spouse:</span> <?= htmlspecialchars($details['spouse']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Buttons leveled in the middle -->
                                <div class="flex gap-2 ml-4">
                                    <button onclick='showDocument(<?= htmlspecialchars(json_encode($row)) ?>)' 
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200 text-sm font-medium min-w-[70px] h-10 flex items-center justify-center"
                                            title="View Document">
                                        View
                                    </button>
                                    
                                    <button onclick='requestCertificate(<?= $row['id'] ?>)' 
                                            class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition duration-200 text-sm font-medium min-w-[70px] h-10 flex items-center justify-center"
                                            title="Request Certificate">
                                        Request
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($searchPerformed): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p class="text-lg">No matching records found.</p>
                        <p class="text-sm mt-2">Try adjusting your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Document View Modal (Hidden by default) -->
    <div id="documentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 no-print">
        <div class="relative top-10 mx-auto p-5 w-full max-w-4xl">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-semibold" id="modalTitle">Sacramental Document</h3>
                    <div class="flex gap-2">
                        <button onclick="printDocument()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 text-sm flex items-center gap-2">
                            <span>🖨️</span> Print
                        </button>
                        <button onclick="closeDocumentModal()" class="text-gray-600 hover:text-gray-800 text-2xl">&times;</button>
                    </div>
                </div>
                <div class="p-6 bg-gray-50" id="documentContent">
                    <!-- Document content will be loaded here -->
                </div>
                <div class="flex justify-end p-4 border-t">
                    <button onclick="closeDocumentModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden printable document container -->
    <div id="printableDocument" class="hidden"></div>

</main>

<script>
$(document).ready(function() {
    let searchTimeout;
    
    // Real-time search function
    function performSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            let type = $('#cert_type').val();
            let name = $('#name').val();
            let date = $('#date').val();
            
            // Validate required fields
            if (!type || !name) {
                return;
            }
            
            // Show loading indicator
            $('#loadingIndicator').removeClass('hidden');
            
            // Make AJAX request
            $.ajax({
                url: window.location.pathname,
                method: 'GET',
                data: {
                    cert_type: type,
                    name: name,
                    date: date,
                    ajax: 1
                },
                success: function(response) {
                    updateResults(response);
                    $('#loadingIndicator').addClass('hidden');
                    $('#resultsContainer').removeClass('hidden');
                },
                error: function() {
                    $('#loadingIndicator').addClass('hidden');
                    showMessage('Error performing search. Please try again.', 'error');
                }
            });
        }, 500);
    }
    
    // Trigger search on input changes
    $('#cert_type, #name, #date').on('input change', function() {
        performSearch();
    });
    
    // Handle form submission
    $('#searchForm').submit(function(e) {
        e.preventDefault();
        performSearch();
    });
    
    // Function to update results
    function updateResults(data) {
        let html = '';
        
        if (data.results && data.results.length > 0) {
            data.results.forEach(function(record) {
                let details = record.details ? JSON.parse(record.details) : {};
                
                html += `
                    <div class="border rounded-lg p-4 hover:shadow-md transition duration-200">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                        ${record.record_type}
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        ID: ${record.id}
                                    </span>
                                </div>
                                
                                <h5 class="font-semibold text-lg">
                                    ${escapeHtml(record.full_name)}
                                </h5>
                                
                                <p class="text-sm text-gray-600 mb-2">
                                    <span class="font-medium">Date:</span> 
                                    ${new Date(record.record_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                </p>
                `;
                
                if (details.birth_place) {
                    html += `<p class="text-sm text-gray-600"><span class="font-medium">Birth Place:</span> ${escapeHtml(details.birth_place)}</p>`;
                }
                if (details.parents) {
                    html += `<p class="text-sm text-gray-600"><span class="font-medium">Parents:</span> ${escapeHtml(details.parents)}</p>`;
                }
                if (details.spouse) {
                    html += `<p class="text-sm text-gray-600"><span class="font-medium">Spouse:</span> ${escapeHtml(details.spouse)}</p>`;
                }
                
                html += `
                            </div>
                            <div class="flex gap-2 ml-4">
                                <button onclick='showDocument(${JSON.stringify(record)})' 
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200 text-sm font-medium min-w-[70px] h-10 flex items-center justify-center">
                                    View
                                </button>
                                
                                <button onclick='requestCertificate(${record.id})' 
                                        class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition duration-200 text-sm font-medium min-w-[70px] h-10 flex items-center justify-center">
                                    Request
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
        } else {
            html = `
                <div class="text-center py-8 text-gray-500">
                    <p class="text-lg">No matching records found.</p>
                    <p class="text-sm mt-2">Try adjusting your search criteria.</p>
                </div>
            `;
        }
        
        $('#recordsList').html(html);
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Function to show messages
    function showMessage(message, type = 'success') {
        const bgColor = type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
        const html = `
            <div class="bg-white rounded-xl shadow-sm p-4 mb-4 ${bgColor}">
                ${escapeHtml(message)}
            </div>
        `;
        $('#messageContainer').html(html);
        setTimeout(() => {
            $('#messageContainer').html('');
        }, 3000);
    }
});

// Function to show document view
function showDocument(record) {
    let details = record.details ? JSON.parse(record.details) : {};
    let documentHtml = '';
    
    // Set modal title
    document.getElementById('modalTitle').textContent = `${record.record_type} Certificate`;
    
    // Generate document based on record type
    switch(record.record_type) {
        case 'Baptism':
            documentHtml = generateBaptismDocument(record, details);
            break;
        case 'Confirmation':
            documentHtml = generateConfirmationDocument(record, details);
            break;
        case 'Marriage':
            documentHtml = generateMarriageDocument(record, details);
            break;
        case 'Funeral':
            documentHtml = generateFuneralDocument(record, details);
            break;
        default:
            documentHtml = generateGenericDocument(record, details);
    }
    
    document.getElementById('documentContent').innerHTML = documentHtml;
    document.getElementById('documentModal').classList.remove('hidden');
}

// Generate Baptism Certificate
function generateBaptismDocument(record, details) {
    const formattedDate = new Date(record.record_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    return `
        <div class="certificate-border bg-white p-8 max-w-3xl mx-auto font-serif">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-blue-900">⚜️ BAPTISM CERTIFICATE ⚜️</h1>
                <p class="text-gray-600 mt-2">"Go therefore and make disciples of all nations, baptizing them in the name of the Father and of the Son and of the Holy Spirit" - Matthew 28:19</p>
            </div>
            
            <div class="border-t-2 border-b-2 border-yellow-600 py-6 my-4">
                <p class="text-center text-lg">This is to certify that</p>
                <p class="text-center text-3xl font-bold my-2">${escapeHtml(record.full_name)}</p>
                <p class="text-center text-lg">was baptized according to the rite of the</p>
                <p class="text-center text-xl font-semibold">HOLY CATHOLIC CHURCH</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 my-6">
                <div>
                    <p><span class="font-semibold">Date of Baptism:</span> ${formattedDate}</p>
                    <p><span class="font-semibold">Place:</span> ${details.birth_place || 'Our Parish Church'}</p>
                    ${details.parents ? `<p><span class="font-semibold">Parents:</span> ${escapeHtml(details.parents)}</p>` : ''}
                </div>
                <div>
                    <p><span class="font-semibold">Minister:</span> Rev. Father John Smith</p>
                    <p><span class="font-semibold">Sponsors:</span> Mr. & Mrs. Michael Johnson</p>
                </div>
            </div>
            
            <div class="mt-8 pt-4 border-t">
                <div class="flex justify-between">
                    <div class="text-center">
                        <p class="font-semibold">_________________________</p>
                        <p>Officiating Minister</p>
                    </div>
                    <div class="text-center">
                        <p class="font-semibold">_________________________</p>
                        <p>Parish Priest</p>
                    </div>
                </div>
                <p class="text-center text-sm text-gray-500 mt-4">Issued on ${today} | Parish Register No. ${record.id}</p>
            </div>
        </div>
    `;
}

// Generate Confirmation Certificate
function generateConfirmationDocument(record, details) {
    const formattedDate = new Date(record.record_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    return `
        <div class="certificate-border bg-white p-8 max-w-3xl mx-auto font-serif">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-red-900">🕊️ CONFIRMATION CERTIFICATE 🕊️</h1>
                <p class="text-gray-600 mt-2">"Now when the apostles at Jerusalem heard that Samaria had received the word of God, they sent to them Peter and John, who came down and prayed for them that they might receive the Holy Spirit" - Acts 8:14-15</p>
            </div>
            
            <div class="border-t-2 border-b-2 border-red-600 py-6 my-4">
                <p class="text-center text-lg">Be it known that</p>
                <p class="text-center text-3xl font-bold my-2">${escapeHtml(record.full_name)}</p>
                <p class="text-center text-lg">received the Sacrament of Confirmation</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 my-6">
                <div>
                    <p><span class="font-semibold">Date of Confirmation:</span> ${formattedDate}</p>
                    <p><span class="font-semibold">Place:</span> ${details.baptized_parish || 'Our Parish Church'}</p>
                    <p><span class="font-semibold">Baptized at:</span> ${details.baptized_parish || 'Same Parish'}</p>
                </div>
                <div>
                    <p><span class="font-semibold">Confirming Bishop:</span>wala pa po</p>
                    <p><span class="font-semibold">Sponsor:</span> ${details.parents || 'Parish Community'}</p>
                </div>
            </div>
            
            <div class="mt-8 pt-4 border-t">
                <div class="flex justify-between">
                    <div class="text-center">
                        <p class="font-semibold">_________________________</p>
                        <p>Confirming Bishop</p>
                    </div>
                    <div class="text-center">
                        <p class="font-semibold">_________________________</p>
                        <p>Parish Priest</p>
                    </div>
                </div>
                <p class="text-center text-sm text-gray-500 mt-4">Issued on ${today} | Parish Register No. ${record.id}</p>
            </div>
        </div>
    `;
}

// Generate Marriage Certificate
function generateMarriageDocument(record, details) {
    const formattedDate = new Date(record.record_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    return `
        <div class="certificate-border bg-white p-8 max-w-3xl mx-auto font-serif">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-pink-900">💍 MARRIAGE CERTIFICATE 💍</h1>
                <p class="text-gray-600 mt-2">"Therefore what God has joined together, let no one separate" - Mark 10:9</p>
            </div>
            
            <div class="border-t-2 border-b-2 border-pink-600 py-6 my-4">
                <p class="text-center text-lg">This certifies that</p>
                <p class="text-center text-3xl font-bold my-2">${escapeHtml(record.full_name)}</p>
                <p class="text-center text-2xl font-semibold my-2">and</p>
                <p class="text-center text-3xl font-bold my-2">${escapeHtml(details.spouse || '[Spouse Name]')}</p>
                <p class="text-center text-lg">were united in Holy Matrimony</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 my-6">
                <div>
                    <p><span class="font-semibold">Date of Marriage:</span> ${formattedDate}</p>
                    <p><span class="font-semibold">Place:</span> Our Parish Church</p>
                </div>
                <div>
                    <p><span class="font-semibold">Officiating Minister:</span> Rev. Father James Wilson</p>
                    <p><span class="font-semibold">Witnesses:</span> ${details.parents || 'Parish Community'}</p>
                </div>
            </div>
            
            <div class="mt-8 pt-4 border-t">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="font-semibold">_________________________</p>
                        <p>Groom</p>
                    </div>
                    <div>
                        <p class="font-semibold">_________________________</p>
                        <p>Bride</p>
                    </div>
                    <div>
                        <p class="font-semibold">_________________________</p>
                        <p>Officiating Minister</p>
                    </div>
                </div>
                <p class="text-center text-sm text-gray-500 mt-4">Issued on ${today} | Parish Register No. ${record.id}</p>
            </div>
        </div>
    `;
}

// Generate Funeral/Memorial Document
function generateFuneralDocument(record, details) {
    const formattedDate = new Date(record.record_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    return `
        <div class="certificate-border bg-white p-8 max-w-3xl mx-auto font-serif">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900">⚰️ FUNERAL / BURIAL RECORD ⚰️</h1>
                <p class="text-gray-600 mt-2">"I am the resurrection and the life. The one who believes in me will live, even though they die" - John 11:25</p>
            </div>
            
            <div class="border-t-2 border-b-2 border-gray-600 py-6 my-4">
                <p class="text-center text-lg">In loving memory of</p>
                <p class="text-center text-3xl font-bold my-2">${escapeHtml(record.full_name)}</p>
                <p class="text-center text-lg">who departed this life</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 my-6">
                <div>
                    <p><span class="font-semibold">Date of Funeral:</span> ${formattedDate}</p>
                    <p><span class="font-semibold">Place of Burial:</span> Parish Cemetery</p>
                    <p><span class="font-semibold">Cause of Death:</span> ${escapeHtml(details.cause || 'Not specified')}</p>
                </div>
                <div>
                    <p><span class="font-semibold">Officiating Minister:</span> Rev. Father David Brown</p>
                    <p><span class="font-semibold">Age at Death:</span> ${details.age || 'Adult'}</p>
                </div>
            </div>
            
            <div class="mt-8 pt-4 border-t">
                <div class="flex justify-between">
                    <div class="text-center">
                        <p class="font-semibold">_________________________</p>
                        <p>Officiating Minister</p>
                    </div>
                    <div class="text-center">
                        <p class="font-semibold">_________________________</p>
                        <p>Family Representative</p>
                    </div>
                </div>
                <p class="text-center text-sm text-gray-500 mt-4">Issued on ${today} | Parish Register No. ${record.id}</p>
                <p class="text-center text-sm italic mt-2">"Eternal rest grant unto them, O Lord, and let perpetual light shine upon them"</p>
            </div>
        </div>
    `;
}

// Generate Generic Document
function generateGenericDocument(record, details) {
    const formattedDate = new Date(record.record_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    return `
        <div class="certificate-border bg-white p-8 max-w-3xl mx-auto font-serif">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-blue-900">⛪ SACRAMENTAL RECORD ⛪</h1>
                <p class="text-gray-600 mt-2">Our Parish Church - Official Document</p>
            </div>
            
            <div class="border-t-2 border-b-2 border-blue-600 py-6 my-4">
                <p class="text-center text-lg">This is to certify that</p>
                <p class="text-center text-3xl font-bold my-2">${escapeHtml(record.full_name)}</p>
                <p class="text-center text-lg">received the Sacrament of ${record.record_type}</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 my-6">
                <div>
                    <p><span class="font-semibold">Date of Sacrament:</span> ${formattedDate}</p>
                    <p><span class="font-semibold">Place:</span> Our Parish Church</p>
                </div>
                <div>
                    <p><span class="font-semibold">Minister:</span> Rev. Father John Smith</p>
                    <p><span class="font-semibold">Parish:</span> Our Parish Community</p>
                </div>
            </div>
            
            <div class="mt-8 pt-4 border-t">
                <div class="flex justify-between">
                    <div class="text-center">
                        <p class="font-semibold">_________________________</p>
                        <p>Parish Priest</p>
                    </div>
                    <div class="text-center">
                        <p class="font-semibold">_________________________</p>
                        <p>Parish Secretary</p>
                    </div>
                </div>
                <p class="text-center text-sm text-gray-500 mt-4">Issued on ${today} | Parish Register No. ${record.id}</p>
            </div>
        </div>
    `;
}

// Request certificate via AJAX
function requestCertificate(recordId) {
    $.ajax({
        url: window.location.pathname,
        method: 'POST',
        data: {
            record_id: recordId,
            ajax_request: 1
        },
        success: function(response) {
            if (response.success) {
                // Show success message without refreshing
                const messageHtml = `
                    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 bg-green-100 text-green-700">
                        ${escapeHtml(response.message)}
                    </div>
                `;
                $('#messageContainer').html(messageHtml);
                setTimeout(() => {
                    $('#messageContainer').html('');
                }, 3000);
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('Error submitting request. Please try again.');
        }
    });
}

// Print document function
function printDocument() {
    // Get the document content from modal
    const documentContent = document.getElementById('documentContent').innerHTML;
    
    // Set it to printable container
    document.getElementById('printableDocument').innerHTML = documentContent;
    document.getElementById('printableDocument').classList.remove('hidden');
    
    // Print
    window.print();
    
    // Hide printable container after printing
    setTimeout(() => {
        document.getElementById('printableDocument').classList.add('hidden');
    }, 1000);
}

function closeDocumentModal() {
    document.getElementById('documentModal').classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    let modal = document.getElementById('documentModal');
    if (event.target == modal) {
        modal.classList.add('hidden');
    }
}

// Helper function for escaping HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>
