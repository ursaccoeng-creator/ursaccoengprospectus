<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
/>

<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "includes/db.php";

/* ================= HTTPS URL ================= */
$site_url =
    "https://" .
    $_SERVER['HTTP_HOST'] .
    "/prospectus_system/";

$current_page = basename($_SERVER['PHP_SELF']);

$student_id  = $_SESSION['student_id'] ?? '';
$full_name   = '';
$profile_img = "img/default.png";

if ($student_id != '') {

    $query = mysqli_query(
        $conn,
        "
        SELECT full_name, profile_image
        FROM students
        WHERE student_id = '$student_id'
        LIMIT 1
        "
    );

    $row = mysqli_fetch_assoc($query);

    $full_name = $row['full_name'] ?? '';

    if (!empty($row['profile_image'])) {
        $profile_img =
            "uploads/" .
            $row['profile_image'];
    }
}

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
    <a
        href="<?php echo $site_url; ?>dashboard.php"
        class="sidebar-logo"
    >

        <img
            src="<?php echo $site_url; ?>img/logo.png"
            class="sidebar-logo-img"
            alt="Logo"
        >

        <div class="sidebar-user">

            <div class="student-id">
                <?php echo htmlspecialchars($student_id); ?>
            </div>

        </div>

    </a>

    <!-- TITLE -->
    <div class="sidebar-title">
        MENU
    </div>

    <!-- MENU -->

    <a
        href="<?php echo $site_url; ?>dashboard.php"
        class="menu-item <?php if ($current_page == 'dashboard.php') echo 'active'; ?>"
    >
        <i class="fa-solid fa-gauge"></i>
        Dashboard
    </a>

    <a
        href="<?php echo $site_url; ?>student_info.php"
        class="menu-item <?php if ($current_page == 'student_info.php') echo 'active'; ?>"
    >
        <i class="fa-solid fa-user-graduate"></i>
        Student Information
    </a>

    <a
        href="<?php echo $site_url; ?>prospectus.php"
        class="menu-item <?php if ($current_page == 'prospectus.php') echo 'active'; ?>"
    >
        <i class="fa-solid fa-book"></i>
        Prospectus
    </a>

    <a
        href="<?php echo $site_url; ?>grades.php"
        class="menu-item <?php if ($current_page == 'grades.php') echo 'active'; ?>"
    >
        <i class="fa-solid fa-pen-to-square"></i>
        Encode Grades
    </a>

    <a
        href="<?php echo $site_url; ?>suggested_enrollment.php"
        class="menu-item <?php if ($current_page == 'suggested_enrollment.php') echo 'active'; ?>"
    >
        <i class="fa-solid fa-list-check"></i>
        Suggested Enrollment
    </a>

    <a
        href="<?php echo $site_url; ?>print_form.php"
        class="menu-item <?php if ($current_page == 'print_form.php') echo 'active'; ?>"
    >
        <i class="fa-solid fa-print"></i>
        Print Form
    </a>

    <!-- PROFILE + LOGOUT -->
    <div class="sidebar-bottom">

        <a
            href="<?php echo $site_url; ?>student_profile.php"
            class="sidebar-profile <?php if ($current_page == 'student_profile.php') echo 'active-profile'; ?>"
        >

            <img
                src="<?php echo $site_url . $profile_img; ?>"
                class="profile-avatar"
                alt="Profile"
            >

            <div>

                <div class="profile-name">
                    <?php echo htmlspecialchars($full_name); ?>
                </div>

                <div class="profile-text">
                    View Profile
                </div>

            </div>

        </a>

        <button
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
                class="cancel-btn"
                onclick="closeLogoutModal()"
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
    z-index:1002;
    cursor:pointer;
}

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
        #1e4f8a 0%,
        #1e5aa8 45%,
        #1f6ed4 100%
    );

    transition:.3s;

    /* DESKTOP SCROLL FIX */
    overflow-y:auto;
    overflow-x:hidden;
    scrollbar-width:thin;
}

.sidebar::-webkit-scrollbar{
    width:6px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:rgba(255,255,255,.35);
    border-radius:10px;
}

.sidebar-logo{
    text-align:center;
    padding:20px 15px 12px;
    text-decoration:none;
    color:#fff;
}

.sidebar-logo-img{
    width:130px;
    margin-bottom:8px;
}

.sidebar-user{
    text-align:center;
}

.student-id{
    font-size:18px;
    font-weight:700;
}

.sidebar-title{
    color:rgba(255,255,255,.7);
    font-size:12px;
    padding:10px 20px;
}

.menu-item{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 20px;
    color:#fff;
    text-decoration:none;
    font-size:14px;
}

.menu-item:hover{
    background:rgba(255,255,255,.12);
}

.menu-item.active{
    background:rgba(255,255,255,.18);
    border-left:4px solid #fff;
}

.sidebar-profile{
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px;
    border-radius:12px;
    color:#fff;
    text-decoration:none;
    margin-bottom:15px;
    transition:.2s;
}

.sidebar-profile:hover{
    background:rgba(255,255,255,.15);
}

.active-profile{
    background:rgba(255,255,255,.20);
}

.profile-avatar{
    width:44px;
    height:44px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid rgba(255,255,255,.4);
    flex-shrink:0;
}

.profile-name{
    font-size:13px;
    font-weight:600;
}

.profile-text{
    font-size:11px;
    opacity:.85;
}

.sidebar-bottom{
    margin-top:auto;
    padding:15px;
    flex-shrink:0;
}

.logout-sidebar{
    display:flex;
    align-items:center;
    gap:10px;
    width:100%;
    background:rgba(255,255,255,.15);
    border:none;
    color:#fff;
    padding:12px;
    border-radius:10px;
    cursor:pointer;
}

.logout-sidebar:hover{
    background:rgba(255,255,255,.25);
}

.logout-modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.4);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:20px;
}

.logout-card{
    background:#fff;
    padding:25px;
    border-radius:14px;
    width:340px;
    text-align:center;
    box-shadow:0 15px 40px rgba(0,0,0,.25);
}

.logout-title{
    font-size:18px;
    font-weight:700;
    margin-bottom:5px;
}

.logout-text{
    font-size:14px;
    margin-bottom:20px;
    color:#555;
}

.logout-actions{
    display:flex;
    gap:10px;
    justify-content:center;
}

.cancel-btn{
    padding:8px 16px;
    border-radius:8px;
    border:1px solid #ccc;
    background:#f5f5f5;
    cursor:pointer;
}

.confirm-btn{
    padding:8px 16px;
    border-radius:8px;
    text-decoration:none;
    color:#fff;

    background:linear-gradient(
        160deg,
        #1e4f8a,
        #1e5aa8,
        #1f6ed4
    );
}

@media (max-width:768px){

    .mobile-header{
        display:flex;
        position:fixed;
        top:0;
        left:0;
        right:0;
        height:60px;
        color:#fff;
        align-items:center;
        justify-content:center;
        font-weight:700;
        z-index:1000;

        background:linear-gradient(
            160deg,
            #1e4f8a,
            #1e5aa8,
            #1f6ed4
        );
    }

    .burger{
        display:block;
    }

    .sidebar{
        left:-260px;
        top:75px;
        height:calc(100vh - 90px);

        overflow-y:auto;
        overflow-x:hidden;
        -webkit-overflow-scrolling:touch;

        padding-bottom:25px;
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

    .sidebar-logo-img{
        width:110px;
    }

    .sidebar-bottom{
        margin-top:20px;
        padding:15px;
    }

    .logout-card{
        width:100%;
        max-width:320px;
        margin:20px;
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