Use Case: OCR-Based Invoice Processing for Expenses
Use Case Name

Automated Expense Entry via OCR (Dutch Invoice Support)

Primary Actor

User (Employee / Expense Submitter)

Supporting System

OCR Service (external or internal)

Trigger

User uploads a PDF or image file of a Dutch invoice on the Expense page.

Preconditions
User is logged into the system
User has access to the Expense page
File upload functionality is available
OCR service is configured and reachable
Postconditions
Expense form is auto-filled with extracted invoice data
User reviews and submits the expense
Main Flow (Happy Path)
User navigates to the Expense page
User uploads an invoice file (PDF or image)
System sends the file to the OCR service
OCR service processes the file and extracts structured data
OCR service returns extracted invoice data, including:
Purchase date
Invoice number
Total amount
Line items (description, quantity, price, VAT if available)
System maps extracted data to the corresponding fields in the Expense form
Expense form is automatically populated
User reviews the pre-filled data
User edits data if necessary
User clicks Submit
Expense is saved and processed as usual
Alternative Flows
A1: OCR Fails to Extract Data
OCR service returns incomplete or no data
System shows a notification: “We couldn’t extract all details. Please fill in manually.”
User manually enters missing information
A2: Low Confidence Extraction
OCR returns data with confidence scores below threshold
System highlights uncertain fields
User verifies and corrects flagged fields
A3: Unsupported File Format
User uploads unsupported file
System shows error: “Unsupported file type. Please upload PDF or image.”
A4: OCR Service Unavailable
System cannot reach OCR service
System shows error: “OCR service unavailable. Please try again later or enter manually.”
Business Rules
Only Dutch invoices are supported initially
Supported formats: PDF, JPG, PNG
Maximum file size limit applies (e.g., 10MB)
OCR must extract at least:
Invoice number
Total amount
Confidence threshold determines auto-fill vs. manual review
Data Mapping (Example)
OCR Field	Expense Field
Invoice Date	Purchase Date
Invoice Number	Reference Number
Total Amount	Total Cost
VAT Amount	Tax Field
Line Items	Expense Items List
Non-Functional Requirements
OCR response time < 5 seconds (target)
GDPR compliance (sensitive invoice data)
Secure file handling and storage
Retry mechanism for OCR failures