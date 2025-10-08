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
