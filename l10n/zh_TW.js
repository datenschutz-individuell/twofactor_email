OC.L10N.register(
    "twofactor_email",
    {
    "Email" : "電子郵件",
    "Authenticate by email" : "透過電子郵件驗證",
    "Login attempt for %s" : "嘗試登入 %s 帳號",
    "Your two-factor authentication code is: %s" : "您的兩階段驗證碼為：%s",
    "If you tried to login, please enter that code on %s. If you did not, somebody else did and knows your your email address or username – and your password!" : "若您嘗試登入，請於 %s 輸入驗證碼。若您並未嘗試登入，則代表有其他人正在嘗試登入，並知道您的電子郵件地址或使用者名稱，以及您的密碼！",
    "Two-Factor email provider" : "兩階段驗證電子郵件提供者",
    "Email two-factor provider" : "電子郵件兩階段驗證提供者",
    "### A Two-Factor-Auth Provider using email\n\nNextcloud supports web logins with a second factor (two-factor authentication,\n2FA). To support a certain type of 2FA, a \"2FA provider\" server app must be\ninstalled. 2FA kicks in after the primary authentication stage (typically\nusername and password) were successful. This provider challenges the user to\nenter a randomly generated authentication code (aka one-time password, OTP,\ncurrently 6 digits). It sends that code to the user's primary email address\nand expects the user to enter it on an additional 2nd step web login page.\n\n### Installation, activation and usage\n\nAs with any 2FA provider, two-factor email must be installed and enabled by a\nNextcloud server admin. Additionally, the Nextcloud must have a working email\nserver configured.\n\nThe user may set up any of the installed providers or even multiple. This\nprovider uses email to send the code and thus can only be enabled if an email\naddress is set in 'Personal info'. Mind that a user may not be able to log in\nif that email address is invalid (or email server setup of the Nextcloud is\nnot working properly).\n\nAdmins with console access may enable and disable this provider for specified\nusers via \"occ\" command. Admins may also enforce 2FA for all users (or specific\ngroups) via \"Admin Settings\". This is a Nextcloud feature and not specific to\nthis provider. If enforced, users with no 2FA are prompted to enable any\ninstalled provider (if they support that feature). This provider supports it\nsince v3. If the admin installs this provider and enforces 2FA, it should be\nensured that each user does have a valid email address.\n\nMind that, once a user enabled any 2FA provider, they can no longer use their\npassword in applications that don't support the web based 2FA login flow. For\nsuch applications, the user needs to create and use app passwords (to be found\nat the bottom of \"Personal Settings/Security\")." : "### 使用電子郵件的兩階段驗證提供者\n\nNextcloud 支援使用第二因素（兩階段驗證，2FA）的網路登入。\n若要支援某種兩階段驗證 (2FA)，必須安裝「2FA 服務提供者」伺服器應用程式。雙因素驗證會在主要驗證階段（通常為使用者名稱與密碼）成功後啟動。\n在完成主要驗證階段（通常為使用者名稱與密碼）並成功通過後，兩階段驗證 (2FA) 便會啟動。\n此服務提供者要求使用者輸入一組隨機生成的驗證碼（亦稱拋棄式密碼，OTP，目前為 6 位數）。\n系統會將該驗證碼發送至使用者的主要電子郵件地址，並要求使用者在第二步的網頁登入頁面中輸入該驗證碼。\n\n### 安裝、啟用與使用方式\n\n與任何兩階段驗證服務提供者一樣，Nextcloud 伺服器管理員必須安裝並啟用兩階段驗證電子郵件功能。\n此外，Nextcloud 必須已設定好可正常運作的電子郵件伺服器。\n\n使用者可以設定任何已安裝的提供者，甚至可以設定多個。\n此服務提供者會透過電子郵件寄送驗證碼，因此必須在「個人資料」中設定電子郵件地址，才能啟用此功能。\n請注意，若該電子郵件地址無效（或 Nextcloud 的電子郵件伺服器設定未正常運作），使用者可能無法登入。\n\n具有終端機存取權限的管理員可透過「occ」命令，為指定使用者啟用或停用此服務提供者。\n管理員亦可透過「管理設定」為所有使用者（或特定群組）強制啟用兩階段驗證。\n這是 Nextcloud 的功能，並非此服務供應者所獨有。\n若強制執行此設定，未啟用兩階段驗證的使用者將收到提示，要求其啟用已安裝的任何服務提供者（前提是該提供者支援此功能）。\n此提供者自 v3 版本起即支援此功能。\n若管理員安裝此服務提供者並強制實施兩階段驗證，應確保每位使用者確實擁有有效的電子郵件地址。\n\n請注意，一旦使用者啟用了任何兩階段驗證服務提供商，便無法再在不支援以網頁為基礎的 2FA 登入流程的應用程式中使用密碼。\n對於這類應用程式，使用者必須建立並使用應用程式密碼（位於「個人設定」→「安全性」頁面的底部）。",
    "You cannot enable two-factor authentication via email. You need to set a primary email address (in your personal settings) first." : "您無法透過電子郵件啟用兩階段驗證。您必須先在個人設定中設定一個主要電子郵件地址。",
    "Could not enable/disable two-factor authentication via email." : "無法透過電子郵件啟用或停用兩階段驗證。",
    "Unhandled error!" : "發生無法處理的錯誤！",
    "Codes will be sent to your primary email address:" : "驗證碼將會寄送至您的主要電子郵件地址：",
    "Proceed" : "繼續",
    "Use two-factor authentication via email" : "使用電子郵件進行兩階段驗證",
    "Apparently your previously configured email address just vanished." : "看來您先前設定的電子郵件地址不知為何消失了。",
    "A new authentication code was just sent. Please enter it:" : "已寄送新的驗證碼。請輸入：",
    "Enter the authentication code that was sent to you:" : "請輸入已寄送給您的驗證碼：",
    "Authentication code" : "驗證碼",
    "Submit" : "提交"
},
"nplurals=1; plural=0;");
