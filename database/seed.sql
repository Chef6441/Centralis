INSERT INTO brokers (
    name,
    contact_name,
    contact_email,
    contact_phone
) VALUES (
    'GoBrokerage',
    'GoBrokerage Team',
    'hello@gobrokerage.com',
    '+61 2 0000 0000'
);

INSERT INTO partners (
    broker_id,
    name,
    contact_name,
    contact_email,
    contact_phone,
    revenue_share_percentage
) VALUES (
    1,
    'Energy Referral Partners',
    'Jordan Matthews',
    'jordan@energyreferralpartners.com.au',
    '+61 2 9999 1111',
    20.0
);

INSERT INTO suppliers (
    name,
    contact_email,
    contact_phone,
    address
) VALUES
    ('Momentum Energy', 'support@momentum.com.au', '+61 3 1234 5678', 'Melbourne, VIC'),
    ('Energy Australia', 'service@energyaustralia.com.au', '+61 3 9876 5432', 'Melbourne, VIC'),
    ('Supplier 3', 'sales@supplier3.com.au', '+61 2 2468 1357', 'Sydney, NSW');

INSERT INTO clients (
    broker_id,
    partner_id,
    business_name,
    contact_name,
    contact_email,
    contact_phone,
    address
) VALUES
    (1, 1, 'Discover 202 Pty Ltd', 'Nelsy Zreik', 'nelsy@discover202.com.au', '+61 2 8888 0000', 'Unit 12, 28 Logistics Drive, Erskine Park, NSW 2759'),
    (1, NULL, 'Direct Industries Pty Ltd', 'Jamie Lee', 'jamie.lee@directindustries.com.au', '+61 3 7777 5555', '45 Market Street, Melbourne, VIC 3000');

INSERT INTO contracts (
    supplier_id,
    broker_id,
    client_id,
    contract_start_date,
    contract_end_date,
    contract_value,
    status
) VALUES
    (1, 1, 1, '2024-10-01', '2026-09-30', 7200, 'Active'),
    (2, 1, 1, '2023-10-01', '2024-09-30', 7850, 'Expired');

INSERT INTO quotes (
    supplier_id,
    broker_id,
    client_id,
    quote_date,
    term_months,
    total_cost,
    status
) VALUES
    (1, 1, 1, '2024-08-01', 24, 7200, 'Accepted'),
    (2, 1, 1, '2024-08-01', 24, 7850, 'Declined'),
    (3, 1, 2, '2024-08-02', 12, 6800, 'Under Review');

INSERT INTO invoices (
    supplier_id,
    contract_id,
    invoice_number,
    invoice_date,
    amount,
    status
) VALUES
    (1, 1, 'INV-2024-001', '2024-10-15', 7200, 'Paid'),
    (2, 2, 'INV-2024-002', '2024-09-30', 7850, 'Pending');

INSERT INTO reports (
    report_identifier,
    report_date,
    customer_business_name,
    customer_contact_name,
    broker_consultant,
    site_nmi,
    site_current_retailer,
    site_contract_end_date,
    site_address_line1,
    site_address_line2,
    site_peak_kwh,
    site_shoulder_kwh,
    site_off_peak_kwh,
    site_total_kwh,
    contract_current_retailer,
    contract_term_months,
    current_cost,
    new_cost,
    validity_period,
    payment_terms
) VALUES (
    '146',
    '2025-09-25',
    'Discover 202 Pty Ltd',
    'Nelsy Zreik',
    'Alex Dechnicz',
    '41039619655',
    'Momentum Energy',
    '2026-09-30',
    'Unit 12, 28 Logistics Drive',
    'Erskine Park, 2759, NSW, Australia',
    20080,
    47832,
    75929,
    143841,
    'Energy Australia',
    24,
    7850,
    7200,
    '30 Days',
    'Net 14'
);

INSERT INTO contract_offers (
    report_id,
    supplier_name,
    term_months,
    peak_rate,
    shoulder_rate,
    off_peak_rate,
    total_cost,
    diff_dollar,
    diff_percentage
) VALUES
    (1, 'Current', 12, 0.093, 0.11071, 0.09319, 14594.36, NULL, NULL),
    (1, 'Supplier 2', 12, 0.11071, 0.11071, 0.09319, 14594.36, 0.0, 100),
    (1, 'Supplier 3', 12, 0.11071, 0.11071, 0.09319, 14594.36, 0.0, 100),
    (1, 'Current', 24, 0.093, 0.11071, 0.09319, 29188.72, NULL, NULL),
    (1, 'Supplier 2', 24, 0.11071, 0.11071, 0.09319, 29188.72, 14594.36, 200),
    (1, 'Supplier 3', 24, 0.11071, 0.11071, 0.09319, 29188.72, 14594.36, 200),
    (1, 'Current', 36, 0.093, 0.11071, 0.09319, 43783.08, NULL, NULL),
    (1, 'Supplier 2', 36, 0.11071, 0.11071, 0.09319, 43783.08, 29188.72, 200),
    (1, 'Supplier 3', 36, 0.11071, 0.11071, 0.09319, 43783.08, 29188.72, 300);

INSERT INTO other_costs (report_id, cost_label, cost_amount) VALUES
    (1, 'Network Costs', 1000.0),
    (1, 'Cost 2', 1000.0),
    (1, 'Cost 3', 1000.0);
