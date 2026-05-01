<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
/>

<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= HTTPS BASE URL ================= */
$site_url =
    "https://" .
    $_SERVER['HTTP_HOST'] .
    "/prospectus_system/";

$display_user = $_SESSION['admin'] ?? 'Admin';
$current = basename($_SERVER['PHP_SELF']);

?>

<!-- MOBILE HEADER -->
<div class="mobile-header">
    Prospectus System
</div>

<!-- BURGER -->
<div
    class="burger"
    onclick="toggleSidebar()"
>
    <i class="fa-solid fa-bars"></i>
</div>

<!-- SIDEBAR -->
<div
    class="sidebar"
    id="sidebar"
>

    <!-- LOGO -->
    <div class="sidebar-logo">

        <img
            src="<?php echo $site_url; ?>img/logo.png"
            alt="Logo"
        >

    </div>

    <!-- MENU -->
    <div class="sidebar-menu">

        <div class="sidebar-title">
            ADMIN DASHBOARD
        </div>

        <a
            href="<?php echo $site_url; ?>admin/admin.php"
            class="menu-item <?php if ($current == 'admin.php') echo 'active'; ?>"
        >
            <i class="fa-solid fa-gauge"></i>
            Home
        </a>

        <a
            href="<?php echo $site_url; ?>admin/student_records.php"
            class="menu-item <?php if ($current == 'student_records.php') echo 'active'; ?>"
        >
            <i class="fa-solid fa-user-graduate"></i>
            Student Records
        </a>

        <a
            href="<?php echo $site_url; ?>admin/class_lists.php"
            class="menu-item <?php if ($current == 'class_lists.php') echo 'active'; ?>"
        >
            <i class="fa-solid fa-chart-line"></i>
            Grades Tracker
        </a>

        <a
            href="<?php echo $site_url; ?>admin/student_print_form.php"
            class="menu-item <?php if ($current == 'student_print_form.php') echo 'active'; ?>"
        >
            <i class="fa-solid fa-print"></i>
            Student Print Form
        </a>

        <a
            href="<?php echo $site_url; ?>admin/academic_settings.php"
            class="menu-item <?php if ($current == 'academic_settings.php') echo 'active'; ?>"
        >
            <i class="fa-solid fa-gear"></i>
            Manage Academic Settings
        </a>

        <a
            href="<?php echo $site_url; ?>admin/curriculum_update.php"
            class="menu-item <?php if ($current == 'curriculum_update.php') echo 'active'; ?>"
        >
            <i class="fa-solid fa-book-open-reader"></i>
            Curriculum Update
        </a>

        <a
            href="<?php echo $site_url; ?>admin/students.php"
            class="menu-item <?php if ($current == 'students.php') echo 'active'; ?>"
        >
            <i class="fa-solid fa-users"></i>
            Manage Students
        </a>

        <a
            href="<?php echo $site_url; ?>admin/subjects.php"
            class="menu-item <?php if ($current == 'subjects.php') echo 'active'; ?>"
        >
            <i class="fa-solid fa-book"></i>
            Manage Subjects
        </a>

    </div>

    <!-- BOTTOM -->
    <div class="sidebar-bottom">

        <a
            href="<?php echo $site_url; ?>admin/admin_profile.php"
            class="sidebar-profile <?php if ($current == 'admin_profile.php') echo 'active-profile'; ?>"
        >

            <i class="fa-solid fa-user profile-icon"></i>

            <div>

                <div class="admin-name">
                    <?php echo htmlspecialchars($display_user); ?>
                </div>

                <div class="profile-text">
                    View Profile
                </div>

            </div>

        </a>

        <button
            type="button"
            onclick="openLogoutModal()"
            class="logout-sidebar"
        >

            <i class="fa-solid fa-right-from-bracket"></i>
            Logout

        </button>

    </div>

</div>

<!-- OVERLAY -->
<div
    class="overlay"
    id="overlay"
    onclick="toggleSidebar()"
></div>

<!-- LOGOUT MODAL -->
<div
    class="logout-modal"
    id="logoutModal"
>

    <div class="logout-card">

        <div class="logout-title">
            Logout
        </div>

        <div class="logout-text">
            Are you sure you want to logout?
        </div>

        <div class="logout-actions">

            <button
                type="button"
                onclick="closeLogoutModal()"
                class="cancel-btn"
            >
                Cancel
            </button>

            <a
                href="<?php echo $site_url; ?>logout.php"
                class="confirm-btn"
            >
                Logout
            </a>

        </div>

    </div>

</div>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html,
body{
    width:100%;
    min-height:100%;
    overflow-x:hidden;
}

body{
    font-family:Arial, Helvetica, sans-serif;
}

/* HEADER */

.mobile-header{
    display:none;
}

.burger{
    display:none;
    position:fixed;
    top:18px;
    left:18px;
    font-size:22px;
    color:#fff;
    z-index:1003;
    cursor:pointer;
}

/* SIDEBAR */

.sidebar{
    width:230px;
    height:calc(100vh - 30px);
    position:fixed;
    top:15px;
    left:15px;
    display:flex;
    flex-direction:column;
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 12px 30px rgba(0,0,0,.18);
    background:linear-gradient(
        160deg,
        #1e4f8a,
        #1e5aa8,
        #1f6ed4
    );
    z-index:1002;
    transition:.25s ease;
}

.sidebar-logo{
    text-align:center;
    padding:20px 10px 10px;
}

.sidebar-logo img{
    width:120px;
    max-width:100%;
}

/* MENU */

.sidebar-menu{
    flex:1;
    overflow-y:auto;
    overflow-x:hidden;
    padding-bottom:10px;
    -webkit-overflow-scrolling:touch;
}

.sidebar-title{
    color:rgba(255,255,255,.70);
    font-size:12px;
    padding:12px 20px 8px;
    letter-spacing:.5px;
}

.menu-item{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 20px;
    color:#fff;
    text-decoration:none;
    font-size:14px;
    transition:.2s ease;
}

.menu-item i{
    width:18px;
    text-align:center;
}

.menu-item:hover{
    background:rgba(255,255,255,.12);
}

.menu-item.active{
    background:rgba(255,255,255,.18);
    border-left:4px solid #fff;
    padding-left:16px;
}

/* BOTTOM */

.sidebar-bottom{
    padding:15px;
    border-top:1px solid rgba(255,255,255,.15);
    flex-shrink:0;
}

.sidebar-profile{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px;
    border-radius:10px;
    text-decoration:none;
    color:#fff;
    margin-bottom:10px;
    transition:.2s ease;
}

.sidebar-profile:hover{
    background:rgba(255,255,255,.12);
}

.active-profile{
    background:rgba(255,255,255,.18);
}

.profile-icon{
    width:34px;
    height:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:50%;
    background:rgba(255,255,255,.25);
    font-size:16px;
    flex-shrink:0;
}

.admin-name{
    font-size:13px;
    font-weight:600;
}

.profile-text{
    font-size:11px;
    opacity:.8;
    margin-top:2px;
}

.logout-sidebar{
    width:100%;
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    background:rgba(255,255,255,.18);
    color:#fff;
    font-size:14px;
}

.logout-sidebar:hover{
    background:rgba(255,255,255,.25);
}

/* OVERLAY */

.overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    z-index:1000;
}

.overlay.active{
    display:block;
}

/* MODAL */

.logout-modal{
    position:fixed;
    inset:0;
    display:none;
    align-items:center;
    justify-content:center;
    background:rgba(0,0,0,.45);
    z-index:9999;
    padding:20px;
}

.logout-card{
    background:#fff;
    padding:25px;
    border-radius:14px;
    width:340px;
    max-width:100%;
    text-align:center;
}

.logout-title{
    font-size:18px;
    font-weight:700;
    margin-bottom:6px;
}

.logout-text{
    font-size:14px;
    margin-bottom:20px;
}

.logout-actions{
    display:flex;
    gap:10px;
}

.cancel-btn,
.confirm-btn{
    flex:1;
    padding:10px;
    border-radius:8px;
    text-decoration:none;
    font-size:14px;
    cursor:pointer;
}

.cancel-btn{
    border:1px solid #ccc;
    background:#f4f4f4;
}

.confirm-btn{
    background:#1e5aa8;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
}

/* MOBILE */

@media (max-width:768px){

    .mobile-header{
        display:flex;
        position:fixed;
        top:0;
        left:0;
        right:0;
        height:60px;
        background:linear-gradient(
            160deg,
            #1e4f8a,
            #1e5aa8,
            #1f6ed4
        );
        color:#fff;
        align-items:center;
        justify-content:center;
        font-weight:700;
        z-index:1001;
    }

    .burger{
        display:block;
    }

    .sidebar{
        left:-260px;
        top:75px;
        height:calc(100vh - 90px);
        max-height:calc(100vh - 90px);
        overflow-y:auto;
        overflow-x:hidden;
        -webkit-overflow-scrolling:touch;
        padding-bottom:20px;
        scrollbar-width:thin;
    }

    .sidebar::-webkit-scrollbar{
        width:4px;
    }

    .sidebar::-webkit-scrollbar-thumb{
        background:rgba(255,255,255,.35);
        border-radius:10px;
    }

    .sidebar.active{
        left:15px;
    }

    .sidebar-menu{
        overflow-y:visible;
        flex:unset;
    }

    .sidebar-bottom{
        margin-top:0;
        flex-shrink:0;
    }

    .logout-actions{
        flex-direction:column;
    }

}

</style>

<script>

function toggleSidebar() {

    document
        .getElementById("sidebar")
        .classList
        .toggle("active");

    document
        .getElementById("overlay")
        .classList
        .toggle("active");
}

function openLogoutModal() {

    document
        .getElementById("logoutModal")
        .style.display = "flex";
}

function closeLogoutModal() {

    document
        .getElementById("logoutModal")
        .style.display = "none";
}

</script>