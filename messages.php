<?php
session_start();
include "db_connect.php";

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];
$active_chat_id = isset($_GET['uid']) ? intval($_GET['uid']) : 0;

// --- HANDLE SENDING A MESSAGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $receiver_id = intval($_POST['receiver_id']);
    $message_text = trim($_POST['message']);

    if (!empty($message_text) && $receiver_id > 0) {
        $insert_sql = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "iis", $current_user_id, $receiver_id, $message_text);
        mysqli_stmt_execute($stmt);
        
        // Redirect to avoid form resubmission on refresh
        header("Location: messages.php?uid=" . $receiver_id);
        exit();
    }
}

// --- MARK MESSAGES AS READ ---
if ($active_chat_id > 0) {
    $update_read = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?";
    $stmt_read = mysqli_prepare($conn, $update_read);
    mysqli_stmt_bind_param($stmt_read, "ii", $active_chat_id, $current_user_id);
    mysqli_stmt_execute($stmt_read);
}

// --- FETCH CONTACTS ---
$contacts = [];
if ($current_role === 'farmer') {
    // Farmer sees transporters they accepted
    $contact_sql = "SELECT DISTINCT u.id, u.name, u.role 
                    FROM users u 
                    JOIN transport_requests tr ON u.id = tr.transporter_id 
                    WHERE tr.farmer_id = '$current_user_id'";
} else {
    // Transporter sees farmers they bid on OR farmers they are currently hauling for
    $contact_sql = "SELECT DISTINCT u.id, u.name, u.role 
                    FROM users u 
                    JOIN transport_requests tr ON u.id = tr.farmer_id 
                    LEFT JOIN job_bids jb ON tr.id = jb.job_id
                    WHERE jb.transporter_id = '$current_user_id' OR tr.transporter_id = '$current_user_id'";
}

$contact_res = $conn->query($contact_sql);
if ($contact_res) {
    while ($row = $contact_res->fetch_assoc()) {
        $contacts[] = $row;
    }
}

// --- FETCH ACTIVE CHAT HISTORY ---
$chat_history = [];
$active_user_name = "Select a conversation";
$active_user_role = "";

if ($active_chat_id > 0) {
    // Get the name of the person we are chatting with
    $name_stmt = mysqli_prepare($conn, "SELECT name, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($name_stmt, "i", $active_chat_id);
    mysqli_stmt_execute($name_stmt);
    $name_res = mysqli_stmt_get_result($name_stmt)->fetch_assoc();
    if ($name_res) {
        $active_user_name = $name_res['name'];
        $active_user_role = $name_res['role'];
    }

    // Get the messages
    $msg_sql = "SELECT * FROM messages 
                WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                ORDER BY created_at ASC";
    $msg_stmt = mysqli_prepare($conn, $msg_sql);
    mysqli_stmt_bind_param($msg_stmt, "iiii", $current_user_id, $active_chat_id, $active_chat_id, $current_user_id);
    mysqli_stmt_execute($msg_stmt);
    $msg_res = mysqli_stmt_get_result($msg_stmt);
    while ($row = $msg_res->fetch_assoc()) {
        $chat_history[] = $row;
    }
}

// Determine dashboard link based on role
$dashboard_link = ($current_role === 'farmer') ? 'farmer_dashboard.php' : 'transporter_dashboard.php';
$theme_color = ($current_role === 'farmer') ? 'green' : 'blue';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> 
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; } 
        /* Custom scrollbar for chat area */
        #chat-box::-webkit-scrollbar { width: 6px; }
        #chat-box::-webkit-scrollbar-track { background: transparent; }
        #chat-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="text-gray-800 flex h-screen overflow-hidden">

    <aside class="w-20 lg:w-64 <?php echo ($current_role === 'transporter') ? 'bg-gray-900 text-gray-300 border-gray-800' : 'bg-white text-gray-600 border-gray-200'; ?> border-r flex flex-col flex-shrink-0 z-20 transition-all">
        <div class="h-16 flex items-center justify-center lg:justify-start lg:px-6 border-b <?php echo ($current_role === 'transporter') ? 'border-gray-800 bg-gray-950' : 'border-gray-200'; ?>">
            <div class="flex items-center gap-2 text-<?php echo $theme_color; ?>-500 text-xl font-bold">
                <i class="fa-solid fa-truck-fast"></i> <span class="hidden lg:block">AgriMove</span>
            </div>
        </div>
        
        <nav class="flex-1 py-6 space-y-2 overflow-y-auto px-3 lg:px-4">
            <a href="<?php echo $dashboard_link; ?>" class="flex items-center justify-center lg:justify-start gap-3 p-3 lg:px-4 hover:bg-<?php echo ($current_role === 'transporter') ? 'gray-800 text-white' : 'gray-50 text-gray-900'; ?> rounded-lg font-medium transition" title="Dashboard">
                <i class="fa-solid fa-house w-5 text-center"></i> <span class="hidden lg:block">Dashboard</span>
            </a>
            <a href="messages.php" class="flex items-center justify-center lg:justify-start gap-3 p-3 lg:px-4 bg-<?php echo $theme_color; ?>-600 text-white rounded-lg font-medium transition shadow-lg shadow-<?php echo $theme_color; ?>-500/20" title="Messages">
                <i class="fa-regular fa-message w-5 text-center"></i> <span class="hidden lg:block">Messages</span>
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden bg-white relative">
        
        <header class="h-16 border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0 bg-white shadow-sm z-10">
            <h1 class="text-xl font-semibold text-gray-800">Messages</h1>
            <div class="w-10 h-10 rounded-full bg-<?php echo $theme_color; ?>-100 flex items-center justify-center text-<?php echo $theme_color; ?>-700 font-bold border border-<?php echo $theme_color; ?>-200">
                <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden">
            
            <div class="w-1/3 max-w-sm border-r border-gray-200 bg-gray-50 flex flex-col">
                <div class="p-4 border-b border-gray-200 bg-white">
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Your Contacts</h2>
                </div>
                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                    <?php if (empty($contacts)): ?>
                        <div class="text-center p-6 text-gray-400 text-sm">
                            <i class="fa-solid fa-user-slash text-2xl mb-2"></i>
                            <p>No active contacts yet.</p>
                            <p class="text-xs mt-1">Bid on a job to start chatting with the farmer!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <?php $is_active = ($contact['id'] == $active_chat_id); ?>
                            <a href="messages.php?uid=<?php echo $contact['id']; ?>" class="flex items-center gap-3 p-3 rounded-lg transition <?php echo $is_active ? 'bg-'.$theme_color.'-50 border border-'.$theme_color.'-200' : 'hover:bg-gray-100 border border-transparent'; ?>">
                                <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center font-bold <?php echo $is_active ? 'bg-'.$theme_color.'-600 text-white' : 'bg-gray-200 text-gray-600'; ?>">
                                    <?php echo strtoupper(substr($contact['name'], 0, 1)); ?>
                                </div>
                                <div class="overflow-hidden">
                                    <p class="font-semibold text-gray-900 truncate <?php echo $is_active ? 'text-'.$theme_color.'-800' : ''; ?>"><?php echo htmlspecialchars($contact['name']); ?></p>
                                    <p class="text-xs text-gray-500 capitalize truncate"><?php echo htmlspecialchars($contact['role']); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-1 flex flex-col bg-white relative">
                <?php if ($active_chat_id == 0): ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-400 p-6 text-center">
                        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center text-4xl mb-4 border border-gray-100">
                            <i class="fa-regular fa-comments"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Your Messages</h3>
                        <p class="text-sm">Select a contact from the sidebar to start chatting.</p>
                    </div>
                <?php else: ?>
                    <div class="h-16 border-b border-gray-100 bg-white flex items-center px-6 shadow-sm z-10 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-<?php echo $theme_color; ?>-100 flex items-center justify-center text-<?php echo $theme_color; ?>-700 font-bold border border-<?php echo $theme_color; ?>-200">
                                <?php echo strtoupper(substr($active_user_name, 0, 1)); ?>
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-900"><?php echo htmlspecialchars($active_user_name); ?></h2>
                                <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($active_user_role); ?></p>
                            </div>
                        </div>
                    </div>

                    <div id="chat-box" class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50/50">
                        <?php if (empty($chat_history)): ?>
                            <div class="text-center text-gray-400 text-sm mt-10">
                                <p>No messages yet. Send a message to start the conversation!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($chat_history as $msg): ?>
                                <?php 
                                    $is_me = ($msg['sender_id'] == $current_user_id); 
                                    $time = date('h:i A', strtotime($msg['created_at']));
                                ?>
                                <div class="flex <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                                    <div class="max-w-[75%]">
                                        <div class="px-4 py-2.5 rounded-2xl <?php echo $is_me ? 'bg-'.$theme_color.'-600 text-white rounded-br-sm' : 'bg-white border border-gray-200 text-gray-800 rounded-bl-sm shadow-sm'; ?>">
                                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        </div>
                                        <p class="text-[10px] text-gray-400 mt-1 <?php echo $is_me ? 'text-right mr-1' : 'ml-1'; ?>"><?php echo $time; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="p-4 bg-white border-t border-gray-200">
                        <form action="messages.php?uid=<?php echo $active_chat_id; ?>" method="POST" class="flex items-end gap-2">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="receiver_id" value="<?php echo $active_chat_id; ?>">
                            
                            <div class="flex-1 relative">
                                <textarea name="message" rows="1" placeholder="Type your message here..." required
                                          class="w-full pl-4 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-full outline-none focus:border-<?php echo $theme_color; ?>-500 focus:ring-1 focus:ring-<?php echo $theme_color; ?>-500 transition resize-none overflow-hidden" 
                                          style="min-height: 48px; max-height: 120px;"
                                          oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                            </div>
                            
                            <button type="submit" class="w-12 h-12 rounded-full bg-<?php echo $theme_color; ?>-600 hover:bg-<?php echo $theme_color; ?>-700 text-white flex items-center justify-center transition shadow-md flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-<?php echo $theme_color; ?>-500">
                                <i class="fa-solid fa-paper-plane relative -left-0.5"></i>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var chatBox = document.getElementById("chat-box");
            if (chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
    </script>
</body>
</html>