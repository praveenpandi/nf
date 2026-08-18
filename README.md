# Revenue Cycle Management (RCM) & Medical Billing Analytics System
## Complete Non-Technical User Guide, Workflow & Formula Reference Manual

---

## 1. System Overview & Purpose

This system is a **Revenue Cycle Management (RCM) & Medical Billing Analytics Platform** created specifically for medical billing specialists, billing managers, practice administrators, and financial directors. 

### Primary Objectives:
- **Centralize Billing Data**: Combine raw billing reports exported from your Practice Management (PM) or Electronic Health Record (EHR) system.
- **Track Practice Financial Health**: Automatically calculate key performance indicators (KPIs) such as total collections, net collection ratios, charge lag, and denial rates.
- **Manage Accounts Receivable (A/R)**: Instantly identify outstanding claims across aging buckets (0–30, 31–60, 61–90, 91–120, and 120+ days) so billing staff can prioritize follow-ups.
- **Prevent Revenue Leakage**: Spot unbilled appointments (missing charges) and track insurance payer reimbursement trends.

---

## 2. Step-by-Step Medical Billing Workflow

The system follows a simple 5-stage workflow designed for daily, weekly, or monthly billing operations.

```
[ Stage 1: Export Reports ]
        │  Export standard Excel reports from EHR / PM system
        ▼
[ Stage 2: Select & Upload ]
        │  Choose Practice Name (e.g., NFLUA) & Date, then drop Excel files
        ▼
[ Stage 3: Auto-Standardize ]
        │  System cleans date formats, headers, and column names automatically
        ▼
[ Stage 4: Financial Calculations ]
        │  System links claims, applies medical billing formulas & KPI math
        ▼
[ Stage 5: Review & Action ]
        │  Analyze Executive Dashboard & drill down into high-risk claims
```

### Stage 1: Export Reports from PM/EHR System
The medical billing team exports raw report files from the practice management software. These include lists of charges, payments, appointments, transaction ledgers, and accounts receivable aging.

### Stage 2: Select Practice & Upload Files
1. Open the upload page in your web browser.
2. Select or enter the **Company / Medical Practice Name** (e.g., `NFLUA`).
3. Select the **Reporting Benchmark Date** (e.g., `2026-08-13`).
4. Select or drag-and-drop the exported Excel files into the designated drop zones.

### Stage 3: Automated Cleaning & Standardizing
Once uploaded, the system performs automatic data preparation behind the scenes:
- **Date Formatting**: Converts all date fields (whether entered as text or numeric Excel date codes) into standard `MM/DD/YYYY` dates.
- **Header Uniformity**: Cleans column names so that slight naming differences between billing systems do not cause errors.
- **Data Categorization**: Automatically routes each file to its corresponding report type (Charges, Payments, A/R, Appointments).

### Stage 4: Claim Linking & Formula Processing
The system automatically links records using unique **Charge IDs**, **Claim IDs**, **Patient Names**, and **Procedure Codes (CPT)**:
- Calculates the age of every unpaid claim from the date it was billed.
- Matches patient payments and insurance remittances to original charges.
- Computes overall practice collection rates, charge lag, and denial percentages.

### Stage 5: Executive Dashboard & Drill-Down Action
1. **View Summary KPIs**: Review top-level collection numbers, net collection ratios, and average charge lag.
2. **Review Monthly Trends**: Analyze month-by-month cash collections split by insurance vs. patient payments.
3. **Drill Down into A/R Aging**: Click on any aging bucket (e.g., **120+ Days A/R**) to display the exact list of patients, procedure codes, dates of service, and outstanding balances requiring immediate appeals or follow-up calls.
4. **Audit Charge Lag**: Click on **Charge Lag Analytics** to view claims with the highest delay between treatment date and billing date.

---

## 3. Input Data Reports & Descriptions

The system processes 8 core medical billing reports:

| Report Name | Description | Key Billing Fields |
| :--- | :--- | :--- |
| **Charges by Created Date** | Master list of all medical services logged into the system, organized by the date the claim was created. | `Charge ID`, `Created Date`, `Date of Service`, `Rendering Provider`, `Location`, `Charge Amount` |
| **Charges by Date of Service** | List of all medical services rendered, organized by the actual patient visit date. | `Charge ID`, `Date of Service`, `Rendering Provider`, `Location`, `Charge Amount` |
| **Accounts Receivable (A/R) Aging** | List of all outstanding unpaid balances owed by insurance or patients. | `Patient Name`, `Procedure Code`, `Date of Service`, `Aging Days`, `Insurance Balance`, `Patient Balance`, `Total Balance` |
| **Baseline Historical A/R** | Historical audit file used to evaluate first-pass payment resolution. | `Charge ID`, `Date of Service`, `Created Date`, `Insurance Payment` |
| **Appointment Analysis** | Summary of all scheduled patient visits, visit statuses, and copays collected at check-in. | `Appointment Date`, `Appointment Type`, `Appointment Status`, `Patient Name`, `Copay Amount` |
| **Missing Charges Report** | List of completed patient appointments that have not yet been converted into billed charges. | `Appointment Date`, `Location`, `Patient Name`, `Non-Charge Flag` |
| **Patient Payments Ledger** | Log of all payments collected directly from patients (copays, deductibles, coinsurance, self-pay). | `Created Date`, `Collected Amount`, `Patient Name` |
| **Transaction Details / Remittances** | Itemized ledger of insurance payments, contractual write-offs, and adjustments by claim and CPT code. | `Claim ID`, `Ledger Date`, `Created Date`, `Procedure Code`, `Payer Name`, `Insurance Payment`, `Adjustment`, `Write-Off` |

---

## 4. Medical Billing Metrics & Formula Reference

*Note: All formulas are presented in plain mathematical terms with non-technical explanations.*

---

### Category A: Financial & Cash Collection Metrics

#### 1. Total Gross Billed Charges ($)
- **Explanation**: The total dollar amount charged for all medical services provided to patients during the selected period, before any insurance write-offs or discounts.
- **Formula**:
  $$\text{Total Gross Billed Charges} = \text{Sum of All Billed Service Amounts}$$

#### 2. Total Insurance Payments ($)
- **Explanation**: The actual cash collected and posted from health insurance plans (payers) for billed claims.
- **Formula**:
  $$\text{Total Insurance Payments} = \text{Sum of All Insurance Payments Received}$$

#### 3. Total Patient Payments ($)
- **Explanation**: The total cash collected directly from patients, including copays, coinsurance, deductibles, and self-pay balances.
- **Formula**:
  $$\text{Total Patient Payments} = \text{Sum of All Patient Collections Received}$$

#### 4. Total Cash Collections ($)
- **Explanation**: The total combined cash collected into the practice from both insurance companies and patients.
- **Formula**:
  $$\text{Total Cash Collections} = \text{Total Insurance Payments} + \text{Total Patient Payments}$$

#### 5. Annualized Gross Revenue ($)
- **Explanation**: The monthly average cash collection rate projected across a 12-month period to measure annual practice revenue pace.
- **Formula**:
  $$\text{Annualized Gross Revenue} = \frac{\text{Total Cash Collections}}{12}$$

#### 6. Contractual Adjustments ($)
- **Explanation**: The total dollar amount written off based on fee schedule agreements between the medical practice and contracted insurance networks (the difference between what was billed and what the insurance allowed).
- **Formula**:
  $$\text{Contractual Adjustments} = \text{Sum of All Insurance Fee Schedule Contractual Adjustments}$$

#### 7. Bad Debt Write-Offs ($)
- **Explanation**: Balances deemed uncollectible and written off by the billing department (e.g., timely filing expiration, uncollectible patient balances).
- **Formula**:
  $$\text{Bad Debt Write-Offs} = \text{Sum of All Write-Off Amounts}$$

#### 8. Gross Collection Rate (%)
- **Explanation**: The percentage of total gross charges that were converted into cash collections, without taking insurance write-offs into account.
- **Formula**:
  $$\text{Gross Collection Rate (\%)} = \left( \frac{\text{Total Cash Collections}}{\text{Total Gross Billed Charges}} \right) \times 100$$

#### 9. Net Collection Ratio (%)
- **Explanation**: The primary benchmark of medical billing efficiency. It measures what percentage of net collectible dollars (gross charges minus contractual insurance write-offs) was successfully collected.
- **Formula**:
  $$\text{Net Collection Ratio (\%)} = \left( \frac{\text{Insurance Payments} + \text{Patient Payments} + \text{Outstanding Patient Balance}}{\text{Total Gross Billed Charges} - \text{Contractual Adjustments}} \right) \times 100$$

---

### Category B: Accounts Receivable (A/R) & Aging Metrics

#### 10. Total Accounts Receivable (A/R) Balance ($)
- **Explanation**: The total unpaid dollar balance currently owed to the medical practice by insurance payers and patients combined.
- **Formula**:
  $$\text{Total A/R Balance} = \text{Insurance A/R Balance} + \text{Patient A/R Balance}$$

#### 11. Insurance Accounts Receivable (Insurance A/R) ($)
- **Explanation**: The total unpaid balance pending payment from health insurance companies.
- **Formula**:
  $$\text{Insurance A/R} = \text{Sum of Outstanding Insurance Balances on Pending Claims}$$

#### 12. Patient Accounts Receivable (Patient A/R) ($)
- **Explanation**: The total unpaid balance owed directly by patients after insurance adjudication or for self-pay services.
- **Formula**:
  $$\text{Patient A/R} = \text{Sum of Outstanding Patient Balances Across All Accounts}$$

#### 13. Claim Age (Aging Days)
- **Explanation**: The number of elapsed days between the date a claim was created/billed and the current reporting date.
- **Formula**:
  $$\text{Claim Aging Days} = \text{Reporting Benchmark Date} - \text{Claim Creation Date}$$

#### 14. A/R Aging Bucket Amounts ($)
- **Explanation**: The sum of unpaid balances categorized by claim age to evaluate collection urgency:
  - **0–30 Days**: Fresh claims currently in standard insurance processing.
  - **31–60 Days**: Claims awaiting payment that require standard status checks.
  - **61–90 Days**: Aging claims requiring direct follow-up calls or documentation.
  - **91–120 Days**: Severely delayed claims requiring formal appeals or manager review.
  - **120+ Days**: Aged claims at high risk of timely filing denials.
- **Formula**:
  $$\text{Bucket A/R Amount} = \text{Sum of Total Balances for Claims within specified Day Range}$$

#### 15. A/R Aging Bucket Percentages (%)
- **Explanation**: The proportion of total insurance A/R represented by a specific aging category.
- **Formula**:
  $$\text{Bucket A/R Percentage (\%)} = \left( \frac{\text{Bucket A/R Amount}}{\text{Total Insurance A/R Balance}} \right) \times 100$$

#### 16. Days in Accounts Receivable (A/R Days)
- **Explanation**: The average number of days it takes for the practice to turn billed medical claims into cash reimbursement from insurance companies.
- **Formula**:
  $$\text{A/R Days} = \frac{\text{Total Insurance A/R Balance}}{\text{Average Daily Billed Charges}}$$
  *where:*
  $$\text{Average Daily Billed Charges} = \frac{\text{Total Gross Billed Charges}}{\text{Total Number of Unique Dates of Service}}$$

---

### Category C: Billing Quality, Timeliness & Denial Metrics

#### 17. Average Charge Lag (Days)
- **Explanation**: The average number of days between when a patient receives medical care (Date of Service) and when the billing team creates and submits the claim. Lower charge lag improves cash flow.
- **Formula**:
  $$\text{Average Charge Lag} = \frac{\text{Sum of (Claim Creation Date } - \text{ Date of Service) across all claims}}{\text{Total Count of Billed Claims}}$$

#### 18. First-Pass Resolution Rate (FPRR) (%)
- **Explanation**: The percentage of submitted claims that were paid by insurance on the first attempt within 30 days without being rejected, denied, or requiring corrections.
- **Formula**:
  $$\text{First-Pass Resolution Rate (\%)} = \left( \frac{\text{Count of Claims Paid on First Submission (within 30 days)}}{\text{Total Count of Submitted Claims}} \right) \times 100$$

#### 19. Insurance Denial Rate (%)
- **Explanation**: The percentage of submitted claims that remain completely unpaid by insurance (zero payment with an active balance) 60 days or more after the date of service.
- **Formula**:
  $$\text{Insurance Denial Rate (\%)} = \left( \frac{\text{Count of Unpaid Claims (Balance } > \$0 \text{ & Payment } = \$0 \text{ after 60+ days)}}{\text{Total Count of Submitted Claims}} \right) \times 100$$

#### 20. Missing Charges Count (Unbilled Visits)
- **Explanation**: The total number of completed patient visits recorded on the schedule where no billable service charge has been created in the billing system.
- **Formula**:
  $$\text{Missing Charges Count} = \text{Count of Completed Patient Appointments with No Linked Billed Charge}$$

#### 21. Total & Average Copay Collections
- **Explanation**: Measures how effectively front-desk staff collect required copay amounts from patients during office check-in.
- **Formulas**:
  $$\text{Total Copay Collected} = \text{Sum of All Copay Amounts Collected at Check-In}$$
  $$\text{Average Copay per Appointment} = \frac{\text{Total Copay Collected}}{\text{Total Completed Patient Appointments}}$$

---

### Category D: Operational & Provider Performance Metrics

#### 22. Physician / Rendering Provider Productivity
- **Explanation**: The volume of medical procedures performed and total billing dollars generated by each rendering physician or provider.
- **Formulas**:
  $$\text{Provider Billed Volume} = \text{Count of Unique Billed Claims for Provider}$$
  $$\text{Provider Billed Value (\$) } = \text{Sum of Billed Charge Amounts for Provider}$$

#### 23. Location-Wise Monthly Billing Breakdown
- **Explanation**: Month-by-month billing charge counts aggregated by clinic or facility location to monitor geographic service demand.
- **Formula**:
  $$\text{Location Monthly Volume} = \text{Count of Unique Claims Billed for Location in Month } M$$

#### 24. Top 10 CPT Procedure Codes by Revenue
- **Explanation**: Ranking of the top 10 medical procedure codes (CPT codes) generating the highest cash collections for the practice.
- **Formula**:
  $$\text{CPT Total Collections} = \text{Sum of Insurance Payments } + \text{ Patient Payments for CPT Code}$$

#### 25. Top 10 Insurance Payers by Revenue
- **Explanation**: Ranking of the top 10 health insurance payers providing the highest cash reimbursements to the practice.
- **Formula**:
  $$\text{Payer Total Collections} = \text{Sum of Insurance Payments Received from Health Plan}$$

---

## 5. Medical Billing Action Matrix

This matrix provides practical steps for billing staff based on dashboard metric outputs:

| Dashboard Indicator | Target / Benchmark | Condition / Warning Sign | Recommended Billing Staff Action |
| :--- | :--- | :--- | :--- |
| **Net Collection Ratio** | 95% – 99% | Below 90% | Audit unpaid balances, review unposted payments, and verify fee schedule adjustment accuracy. |
| **A/R Days** | < 35 Days | Exceeds 45 Days | Contact insurance payers with claims pending over 30 days; prioritize high-dollar balances. |
| **120+ Days A/R %** | < 10% | Exceeds 15% | Pull drill-down list of 120+ day claims; submit immediate appeals before timely filing limits expire. |
| **Average Charge Lag** | < 3 Days | Exceeds 5 Days | Remind clinical providers to complete and sign encounter notes promptly after patient visits. |
| **First-Pass Resolution** | > 90% | Below 80% | Review claim rejection logs; correct claim scrub rules for missing NPIs, invalid CPT/ICD codes, or subscriber IDs. |
| **Insurance Denial Rate** | < 5% | Exceeds 10% | Categorize denials by root cause (Eligibility, Pre-authorization, Medical Necessity) and train front-desk/billing staff. |
| **Missing Charges** | 0 | Greater than 0 | Cross-reference appointment schedule daily with charge entry log to ensure every visit is billed. |

---
