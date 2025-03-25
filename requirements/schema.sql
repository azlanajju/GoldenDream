
-- 1. Admins Table (Parent Table)
CREATE TABLE Admins (
    AdminID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Email VARCHAR(255) UNIQUE NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    Role ENUM('SuperAdmin', 'Verifier') DEFAULT 'Verifier',
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Schemes Table (Stores types of schemes)
CREATE TABLE Schemes (
    SchemeID INT AUTO_INCREMENT PRIMARY KEY,
    SchemeName VARCHAR(255) UNIQUE NOT NULL,
    Description TEXT,
    MonthlyPayment DECIMAL(10,2) NOT NULL,
    TotalPayments INT NOT NULL DEFAULT 1,
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Promoters Table (Parent Table)
CREATE TABLE Promoters (
    PromoterID INT AUTO_INCREMENT PRIMARY KEY,
    PromoterUniqueID VARCHAR(50) UNIQUE NOT NULL,
    CustomerID INT UNIQUE,
    Name VARCHAR(255) NOT NULL,
    Contact VARCHAR(50) UNIQUE NOT NULL,
    Email VARCHAR(255) UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    Address TEXT,
    ProfileImageURL VARCHAR(255),
    BankAccountName VARCHAR(255),
    BankAccountNumber VARCHAR(50),
    IFSCCode VARCHAR(20),
    BankName VARCHAR(255),
    PaymentCodeCounter INT DEFAULT 0,
    TotalEarnings DECIMAL(10,2) DEFAULT 0.00,
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Customers Table (Child of Promoters)
CREATE TABLE Customers (
    CustomerID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerUniqueID VARCHAR(50) UNIQUE NOT NULL,
    Name VARCHAR(255) NOT NULL,
    Contact VARCHAR(50) UNIQUE NOT NULL,
    Email VARCHAR(255) UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    Address TEXT,
    ProfileImageURL VARCHAR(255),
    BankAccountName VARCHAR(255),
    BankAccountNumber VARCHAR(50),
    IFSCCode VARCHAR(20),
    BankName VARCHAR(255),
    PromoterID INT,
    SchemeID INT,
    PaymentCodes INT DEFAULT 0,
    TotalAmountPaid DECIMAL(10,2) DEFAULT 0.00,
    LastPaymentDate DATETIME DEFAULT NULL,
    Status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (PromoterID) REFERENCES Promoters(PromoterID) ON DELETE SET NULL,
    FOREIGN KEY (SchemeID) REFERENCES Schemes(SchemeID) ON DELETE SET NULL
);

-- 5. Payments Table (Child of Customers and Promoters)
CREATE TABLE Payments (
    PaymentID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT,
    PromoterID INT,
    AdminID INT DEFAULT NULL,
    SchemeID INT,
    Amount DECIMAL(10,2) NOT NULL,
    PaymentCodeValue INT DEFAULT 0,
    ScreenshotURL VARCHAR(255) NOT NULL,
    Status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    VerifiedAt TIMESTAMP NULL,
    FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (PromoterID) REFERENCES Promoters(PromoterID) ON DELETE CASCADE,
    FOREIGN KEY (AdminID) REFERENCES Admins(AdminID) ON DELETE SET NULL,
    FOREIGN KEY (SchemeID) REFERENCES Schemes(SchemeID) ON DELETE SET NULL
);


-- 6. Payment Code Transactions (Child of Payments)
CREATE TABLE PaymentCodeTransactions (
    TransactionID INT AUTO_INCREMENT PRIMARY KEY,
    PromoterID INT,
    AdminID INT DEFAULT NULL,
    PaymentCodeChange INT NOT NULL,
    TransactionType ENUM('Addition', 'Correction', 'Deduction') DEFAULT 'Addition',
    Remarks TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (PromoterID) REFERENCES Promoters(PromoterID) ON DELETE CASCADE,
    FOREIGN KEY (AdminID) REFERENCES Admins(AdminID) ON DELETE SET NULL
);

-- 7. Payment Codes Per Month Table (For Promoters)
CREATE TABLE PaymentCodesPerMonth (
    RecordID INT AUTO_INCREMENT PRIMARY KEY,
    PromoterID INT,
    MonthYear DATE NOT NULL,
    PaymentCodes INT DEFAULT 0,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (PromoterID) REFERENCES Promoters(PromoterID) ON DELETE CASCADE
);

-- 8. Notifications Table (Linked to all users)
CREATE TABLE Notifications (
    NotificationID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    UserType ENUM('Customer', 'Promoter', 'Admin') NOT NULL,
    Message TEXT NOT NULL,
    IsRead BOOLEAN DEFAULT FALSE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Activity Logs Table (Logs actions from Admins and Promoters)
CREATE TABLE ActivityLogs (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    UserType ENUM('Admin', 'Promoter') NOT NULL,
    Action TEXT NOT NULL,
    IPAddress VARCHAR(50),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. Subscriptions Table (Tracks customer subscriptions)
CREATE TABLE Subscriptions (
    SubscriptionID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL,
    SchemeID INT NOT NULL,
    StartDate DATE NOT NULL,
    EndDate DATE NOT NULL,
    RenewalStatus ENUM('Active', 'Expired', 'Cancelled') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (SchemeID) REFERENCES Schemes(SchemeID) ON DELETE CASCADE
);

-- 10. Payment QR Table (Stores payment details for customers)
CREATE TABLE PaymentQR (
    QRID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT,
    UPIQRImageURL VARCHAR(255) NOT NULL,
    BankAccountName VARCHAR(255) NOT NULL,
    BankAccountNumber VARCHAR(50) NOT NULL,
    IFSCCode VARCHAR(20) NOT NULL,
    BankName VARCHAR(255) NOT NULL,
    BankBranch VARCHAR(255) NOT NULL,
    BankAddress TEXT NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE CASCADE
);
