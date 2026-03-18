<?php
session_start();
include "db_connect.php";

// Ensure only logged-in users can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Get user details for the top nav
$nameRes = $conn->query("SELECT name FROM users WHERE id='$farmer_id'");
$name = ($nameRes && $nameRes->num_rows > 0) ? $nameRes->fetch_assoc()['name'] : 'Farmer';

// Determine the active chat (if a user ID is passed in the URL)
$active_chat_id = isset($_GET['uid']) ? intval($_GET['uid']) : 0;

// HANDLE SENDING A NEW MESSAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $active_chat_id > 0) {
    $message_text = trim($_POST['message']);
    if (!empty($message_text)) {
        $insert_sql = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iis", $farmer_id, $active_chat_id, $message_text);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Redirect to avoid form resubmission on refresh
        header("Location: messages.php?uid=" . $active_chat_id);
        exit();
    }
}

// FETCH CONVERSATIONS SAFELY
$conv_sql = "
    SELECT u.id, u.name, u.role, MAX(m.created_at) as last_msg_time
    FROM users u
    JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.id)
    WHERE u.id != ?
    GROUP BY u.id, u.name, u.role
    ORDER BY last_msg_time DESC
";

$conv_stmt = mysqli_prepare($conn, $conv_sql);

// Safety Check: If the messages table doesn't exist, stop and warn the user safely
if (!$conv_stmt) {
    die("<div style='padding:20px; font-family:sans-serif; text-align:center;'>
            <h2 style='color:red;'>Database Error</h2>
            <p>It looks like the <b>messages</b> table does not exist in your database yet.</p>
            <p>Please run the CREATE TABLE script for `messages` in phpMyAdmin.</p>
            <p>Error details: " . mysqli_error($conn) . "</p>
         </div>");
}

mysqli_stmt_bind_param($conv_stmt, "iii", $farmer_id, $farmer_id, $farmer_id);
mysqli_stmt_execute($conv_stmt);
$conversations_result = mysqli_stmt_get_result($conv_stmt);

// Fetch unread count and last message properly for each conversation
$conversations = [];
while ($row = $conversations_result->fetch_assoc()) {
    $other_user_id = $row['id'];
    
    // Get last message text
    $last_msg_sql = "SELECT message FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1";
    $lm_stmt = mysqli_prepare($conn, $last_msg_sql);
    mysqli_stmt_bind_param($lm_stmt, "iiii", $other_user_id, $farmer_id, $farmer_id, $other_user_id);
    mysqli_stmt_execute($lm_stmt);
    $lm_res = mysqli_stmt_get_result($lm_stmt);
    $row['last_msg'] = ($lm_res->num_rows > 0) ? $lm_res->fetch_assoc()['message'] : "";
    mysqli_stmt_close($lm_stmt);
    
    // Get unread count
    $unread_sql = "SELECT COUNT(*) as unread FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
    $un_stmt = mysqli_prepare($conn, $unread_sql);
    mysqli_stmt_bind_param($un_stmt, "ii", $other_user_id, $farmer_id);
    mysqli_stmt_execute($un_stmt);
    $un_res = mysqli_stmt_get_result($un_stmt);
    $row['unread_count'] = ($un_res->num_rows > 0) ? $un_res->fetch_assoc()['unread'] : 0;
    mysqli_stmt_close($un_stmt);
    
    $conversations[] = $row;
}
mysqli_stmt_close($conv_stmt);

// If no active chat is selected, but conversations exist, default to the most recent one
if ($active_chat_id === 0 && count($conversations) > 0) {
    $active_chat_id = $conversations[0]['id'];
}

// FETCH ACTIVE CHAT DETAILS & MESSAGES
$active_user_name = "Select a conversation";
$active_user_role = "";
$chat_messages = [];

if ($active_chat_id > 0) {
    // Get details of the person we are chatting with
    $user_sql = "SELECT name, role FROM users WHERE id = ?";
    $u_stmt = mysqli_prepare($conn, $user_sql);
    if ($u_stmt) {
        mysqli_stmt_bind_param($u_stmt, "i", $active_chat_id);
        mysqli_stmt_execute($u_stmt);
        $u_res = mysqli_stmt_get_result($u_stmt);
        if ($u_res->num_rows > 0) {
            $u_data = $u_res->fetch_assoc();
            $active_user_name = $u_data['name'];
            $active_user_role = ucfirst($u_data['role']);
        }
        mysqli_stmt_close($u_stmt);
    }

    // Mark messages as read
    $read_sql = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?";
    $read_stmt = mysqli_prepare($conn, $read_sql);
    if ($read_stmt) {
        mysqli_stmt_bind_param($read_stmt, "ii", $active_chat_id, $farmer_id);
        mysqli_stmt_execute($read_stmt);
        mysqli_stmt_close($read_stmt);
    }

    // Fetch message history
    $msg_sql = "SELECT * FROM messages 
                WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                ORDER BY created_at ASC";
    $msg_stmt = mysqli_prepare($conn, $msg_sql);
    if ($msg_stmt) {
        mysqli_stmt_bind_param($msg_stmt, "iiii", $farmer_id, $active_chat_id, $active_chat_id, $farmer_id);
        mysqli_stmt_execute($msg_stmt);
        
        // Load messages into an array
        $msg_res = mysqli_stmt_get_result($msg_stmt);
        while ($m = $msg_res->fetch_assoc()) {
            $chat_messages[] = $m;
        }
        mysqli_stmt_close($msg_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .chat-container { height: calc(100vh - 4rem); } /* 100vh minus header height */
    </style>
</head>
<body class="text-gray-800 flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col hidden md:flex flex-shrink-0 z-20">
        <div class="h-16 flex items-center px-6 border-b border-gray-200">
            <div class="flex items-center gap-2 text-green-600 text-xl font-bold">
                <i class="fa-solid fa-truck-fast"></i> AgriMove
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="farmer_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-house w-5"></i> Dashboard
            </a>
            <a href="create_request.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-plus w-5"></i> Create Request
            </a>
            <a href="my_requests.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-list w-5"></i> My Requests
            </a>
            
            <a href="messages.php" class="flex items-center gap-3 px-4 py-3 bg-green-50 text-green-700 rounded-lg font-medium transition">
                <i class="fa-regular fa-message w-5"></i> Messages
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-gear w-5"></i> Settings
            </a>
        </nav>

        <div class="p-4 border-t border-gray-200">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div class="flex items-center gap-4">
                <button class="md:hidden text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="text-xl font-semibold text-gray-800 hidden sm:block">Messages</h1>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($name); ?></p>
                        <p class="text-xs text-gray-500">Farmer Account</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-bold border border-green-200">
                        <?php echo strtoupper(substr($name, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 flex bg-white chat-container">
            
            <div class="w-full md:w-80 border-r border-gray-200 flex flex-col <?php echo ($active_chat_id > 0) ? 'hidden md:flex' : 'flex'; ?>">
                <div class="p-4 border-b border-gray-100 bg-gray-50">
                    <div class="relative">
                        <i class="fa-solid fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                        <input type="text" placeholder="Search messages..." class="w-full pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500">
                    </div>
                </div>
                
                <div class="flex-1 overflow-y-auto">
                    <?php if (count($conversations) > 0): ?>
                        <?php foreach($conversations as $conv): ?>
                            <a href="messages.php?uid=<?php echo $conv['id']; ?>" class="block border-b border-gray-100 p-4 transition <?php echo ($active_chat_id == $conv['id']) ? 'bg-green-50' : 'hover:bg-gray-50'; ?>">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-full flex-shrink-0 flex items-center justify-center font-bold text-lg 
                                        <?php echo ($conv['role'] == 'transporter') ? 'bg-blue-100 text-blue-700 border border-blue-200' : 'bg-gray-100 text-gray-700 border border-gray-200'; ?>">
                                        <?php echo strtoupper(substr($conv['name'], 0, 1)); ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-baseline mb-1">
                                            <h3 class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($conv['name']); ?></h3>
                                            <span class="text-xs text-gray-400 flex-shrink-0"><?php echo date("M d", strtotime($conv['last_msg_time'])); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <p class="text-xs text-gray-500 truncate pr-2"><?php echo htmlspecialchars($conv['last_msg']); ?></p>
                                            <?php if($conv['unread_count'] > 0): ?>
                                                <span class="w-5 h-5 bg-green-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center flex-shrink-0">
                                                    <?php echo $conv['unread_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <div class="text-4xl text-gray-200 mb-3"><i class="fa-regular fa-comments"></i></div>
                            <p class="text-sm">No conversations yet.</p>
                            <p class="text-xs mt-1">When a transporter accepts your request, you can chat with them here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-1 flex flex-col bg-gray-50/50 <?php echo ($active_chat_id == 0) ? 'hidden md:flex' : 'flex'; ?>">
                
                <?php if ($active_chat_id > 0): ?>
                    <div class="h-16 px-6 border-b border-gray-200 bg-white flex items-center justify-between flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <a href="messages.php" class="md:hidden text-gray-500 hover:text-gray-900 mr-2">
                                <i class="fa-solid fa-arrow-left"></i>
                            </a>
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold border border-blue-200">
                                <?php echo strtoupper(substr($active_user_name, 0, 1)); ?>
                            </div>
                            <div>
                                <h2 class="font-semibold text-gray-900"><?php echo htmlspecialchars($active_user_name); ?></h2>
                                <p class="text-xs text-green-600 font-medium"><?php echo $active_user_role; ?></p>
                            </div>
                        </div>
                        <div>
                            <button class="text-gray-400 hover:text-gray-600 p-2"><i class="fa-solid fa-phone"></i></button>
                            <button class="text-gray-400 hover:text-gray-600 p-2"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                        </div>
                    </div>

                    <div class="flex-1 p-6 overflow-y-auto" id="chatContainer">
                        <div class="flex flex-col space-y-4">
                            
                            <?php if (count($chat_messages) > 0): ?>
                                <?php 
                                $last_date = '';
                                foreach($chat_messages as $msg): 
                                    $msg_date = date("F j, Y", strtotime($msg['created_at']));
                                    $msg_time = date("g:i A", strtotime($msg['created_at']));
                                    $is_mine = ($msg['sender_id'] == $farmer_id);
                                    
                                    // Show date separator if day changes
                                    if ($msg_date !== $last_date): 
                                        $last_date = $msg_date;
                                ?>
                                    <div class="flex justify-center my-4">
                                        <span class="bg-gray-200 text-gray-600 text-xs font-medium px-3 py-1 rounded-full shadow-sm">
                                            <?php echo ($msg_date == date("F j, Y")) ? "Today" : $msg_date; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="flex <?php echo $is_mine ? 'justify-end' : 'justify-start'; ?>">
                                    <div class="max-w-[75%] md:max-w-[60%]">
                                        <div class="p-3 rounded-2xl shadow-sm relative <?php echo $is_mine ? 'bg-green-600 text-white rounded-br-none' : 'bg-white text-gray-800 border border-gray-100 rounded-bl-none'; ?>">
                                            <p class="text-sm leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($msg['message']); ?></p>
                                        </div>
                                        <p class="text-[10px] text-gray-400 mt-1 <?php echo $is_mine ? 'text-right' : 'text-left'; ?>">
                                            <?php echo $msg_time; ?>
                                            <?php if($is_mine): ?>
                                                <i class="fa-solid <?php echo $msg['is_read'] ? 'fa-check-double text-blue-500' : 'fa-check text-gray-400'; ?> ml-1"></i>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="h-full flex flex-col items-center justify-center text-gray-500 opacity-70">
                                    <i class="fa-regular fa-handshake text-5xl mb-4 text-gray-300"></i>
                                    <p class="text-sm font-medium">This is the beginning of your chat history.</p>
                                    <p class="text-xs">Send a message to discuss transport details.</p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="bg-white border-t border-gray-200 p-4">
                        <form action="messages.php?uid=<?php echo $active_chat_id; ?>" method="POST" class="flex gap-3">
                            <button type="button" class="text-gray-400 hover:text-green-600 transition p-2 flex-shrink-0">
                                <i class="fa-solid fa-paperclip text-lg"></i>
                            </button>
                            <input type="text" name="message" placeholder="Type your message here..." required autocomplete="off"
                                class="flex-1 bg-gray-100 border-transparent rounded-full px-5 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:bg-white transition">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 transition shadow-sm transform hover:scale-105">
                                <i class="fa-solid fa-paper-plane text-sm -ml-1"></i>
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-center p-8 text-gray-500">
                        <div class="w-24 h-24 bg-green-50 rounded-full flex items-center justify-center text-green-500 text-4xl mb-4">
                            <i class="fa-regular fa-comments"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Your Messages</h3>
                        <p class="max-w-xs">Select a conversation from the left menu to start chatting with transporters.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const chatContainer = document.getElementById('chatContainer');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>