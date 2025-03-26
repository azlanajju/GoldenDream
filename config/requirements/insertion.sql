INSERT INTO Admins (Name, Email, PasswordHash, Role, Status)  
VALUES ('ajju', 'ajju@example.com', 'd74ff0ee8da3b9806b18c877dbf29bbde50b5bd8e4dad7a3a725000feb82e8f1', 'SuperAdmin', 'Active');
--Azl@n2002

-- Insert into Admins
INSERT INTO Admins (Name, Email, PasswordHash, Role, Status) VALUES
('Super Admin', 'admin@example.com', '$2y$10$abcdef...', 'SuperAdmin', 'Active'),
('Verifier One', 'verifier1@example.com', '$2y$10$ghijkl...', 'Verifier', 'Active');

-- Insert into Schemes
INSERT INTO Schemes (SchemeName, Description, MonthlyPayment, TotalPayments, Status) VALUES
('Gold Plan', 'Gold membership scheme', 1000.00, 12, 'Active'),
('Silver Plan', 'Silver membership scheme', 500.00, 6, 'Active');

-- Insert into Installments
INSERT INTO Installments (SchemeID, InstallmentName, InstallmentNumber, Amount, DrawDate, Benefits, Status) VALUES
(1, 'First Installment', 1, 1000.00, '2025-04-01', 'Early benefits', 'Active'),
(2, 'First Installment', 1, 500.00, '2025-04-01', 'Basic benefits', 'Active');

-- Insert into Promoters
INSERT INTO Promoters (PromoterUniqueID, Name, Contact, Email, PasswordHash, Status) VALUES
('PROMO123', 'John Promoter', '9876543210', 'john.promoter@example.com', '$2y$10$mnopqr...', 'Active');

-- Insert into Customers
INSERT INTO Customers (CustomerUniqueID, Name, Contact, Email, PasswordHash, PromoterID, Status) VALUES
('CUST001', 'Alice Customer', '9876500001', 'alice@example.com', '$2y$10$stuvwx...', 1, 'Active');

-- Insert into Payments
INSERT INTO Payments (CustomerID, PromoterID, SchemeID, Amount, ScreenshotURL, Status) VALUES
(1, 1, 1, 1000.00, 'screenshot1.jpg', 'Pending');

-- Insert into Payment Code Transactions
INSERT INTO PaymentCodeTransactions (PromoterID, PaymentCodeChange, TransactionType, Remarks) VALUES
(1, 10, 'Addition', 'Initial bonus');

-- Insert into Payment Codes Per Month
INSERT INTO PaymentCodesPerMonth (PromoterID, MonthYear, PaymentCodes) VALUES
(1, '2025-03-01', 10);

-- Insert into Notifications
INSERT INTO Notifications (UserID, UserType, Message) VALUES
(1, 'Customer', 'Your payment is pending approval.');

-- Insert into Activity Logs
INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES
(1, 'Admin', 'Verified a payment', '192.168.1.1');

-- Insert into Subscriptions
INSERT INTO Subscriptions (CustomerID, SchemeID, StartDate, EndDate, RenewalStatus) VALUES
(1, 1, '2025-03-01', '2026-03-01', 'Active');

-- Insert into PaymentQR
INSERT INTO PaymentQR (CustomerID, UPIQRImageURL, BankAccountName, BankAccountNumber, IFSCCode, BankName, BankBranch, BankAddress) VALUES
(1, 'qr_code.jpg', 'Alice Customer', '1234567890', 'IFSC001', 'XYZ Bank', 'Main Branch', '123 Street, City');

-- Insert into Withdrawals
INSERT INTO Withdrawals (UserID, UserType, Amount, Status) VALUES
(1, 'Customer', 500.00, 'Pending');

-- Insert into Winners
INSERT INTO Winners (UserID, UserType, PrizeType, Status) VALUES
(1, 'Customer', 'Gift Hamper', 'Pending');

-- Insert into Teams
INSERT INTO Teams (TeamUniqueID, TeamName) VALUES
('TEAM001', 'Elite Squad');
