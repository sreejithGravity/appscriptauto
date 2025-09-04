// Google Apps Script code (Code.gs)
// Attach this script to your Google Sheet (Extensions → Apps Script).
// 1) Run setup() once to create sheets, headers, and formulas.
// 2) Deploy → Web app → Execute as: Me, Who has access: Anyone with the link (or your domain).
// 3) Open the Web App URL and submit transactions via the HTML form.

const SHEET_TRANSACTIONS = 'Transactions';
const SHEET_META = 'Meta';
const SHEET_MONTHLY = 'Monthly Summary';
const SHEET_BALANCES = 'Account Balances';

/**
 * One-time setup to create required sheets, headers, and formulas.
 * Safe to re-run; it won't duplicate existing sheets.
 */
function setup() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Create or get Transactions sheet
  let tx = ss.getSheetByName(SHEET_TRANSACTIONS);
  if (!tx) {
    tx = ss.insertSheet(SHEET_TRANSACTIONS);
  } else {
    tx.clear();
  }

  const headers = [
    'Type',          // A (Income/Expense)
    'Date',          // B
    'Category',      // C
    'Subcategory',   // D
    'Amount',        // E (positive number)
    'Account',       // F (e.g., Cash, Bank, Card)
    'Payment Method',// G (e.g., Cash, UPI, Card, Bank Transfer)
    'Tags',          // H (comma-separated)
    'Description',   // I
    'Receipt URL',   // J
    'Created At'     // K (timestamp)
  ];
  tx.getRange(1,1,1,headers.length).setValues([headers]);
  tx.setFrozenRows(1);
  tx.getRange("B:B").setNumberFormat("yyyy-mm-dd");
  tx.getRange("E:E").setNumberFormat("#,##0.00");

  // Add Signed Amount helper column L using ARRAYFORMULA
  tx.getRange("L1").setValue("Signed");
  tx.getRange("L2").setFormula('=ARRAYFORMULA(IF(ROW(A:A)=1,"Signed",IF(A:A="","",IF(A:A="Expense",-E:E,E:E))))');

  // Create or get Meta sheet
  let meta = ss.getSheetByName(SHEET_META);
  if (!meta) {
    meta = ss.insertSheet(SHEET_META);
  } else {
    meta.clear();
  }

  // Seed Meta with defaults: Type, Category, Subcategory, Accounts, Payment Methods
  // Columns: A=Type, B=Category, C=Subcategory
  meta.getRange(1,1,1,3).setValues([['Type','Category','Subcategory']]);
  const seed = [
    ['Income','Salary','Monthly'],
    ['Income','Business','Sales'],
    ['Income','Interest','Bank'],
    ['Income','Other','Misc'],

    ['Expense','Food','Restaurants'],
    ['Expense','Food','Groceries'],
    ['Expense','Transport','Fuel'],
    ['Expense','Transport','Taxi'],
    ['Expense','Utilities','Electricity'],
    ['Expense','Utilities','Internet'],
    ['Expense','Housing','Rent'],
    ['Expense','Health','Medicine'],
    ['Expense','Entertainment','Movies'],
    ['Expense','Other','Misc']
  ];
  meta.getRange(2,1,seed.length,3).setValues(seed);

  // Accounts (E column), Payment Methods (F column)
  meta.getRange(1,5,1,2).setValues([['Accounts','Payment Methods']]);
  const accounts = [['Cash'],['Bank - HDFC'],['Bank - SBI'],['Wallet'],['Credit Card']];
  const payMethods = [['Cash'],['UPI'],['Card'],['Bank Transfer'],['Cheque']];
  meta.getRange(2,5,accounts.length,1).setValues(accounts);
  meta.getRange(2,6,payMethods.length,1).setValues(payMethods);

  // Named ranges for validation
  const metaRange = meta.getRange(1,1,meta.getLastRow(),meta.getLastColumn());
  ss.setNamedRange('Meta_All', metaRange);
  ss.setNamedRange('Meta_Type', meta.getRange("A2:A"));
  ss.setNamedRange('Meta_Accounts', meta.getRange("E2:E"));
  ss.setNamedRange('Meta_PaymentMethods', meta.getRange("F2:F"));

  // Data Validations
  const dvBuilder = SpreadsheetApp.newDataValidation().requireValueInRange(ss.getRangeByName('Meta_Type'), true);
  tx.getRange("A2:A").setDataValidation(dvBuilder.build());

  const dvAcc = SpreadsheetApp.newDataValidation().requireValueInRange(ss.getRangeByName('Meta_Accounts'), true);
  tx.getRange("F2:F").setDataValidation(dvAcc.build());

  const dvPay = SpreadsheetApp.newDataValidation().requireValueInRange(ss.getRangeByName('Meta_PaymentMethods'), true);
  tx.getRange("G2:G").setDataValidation(dvPay.build());

  // Create Monthly Summary sheet with a formula using QUERY
  let monthly = ss.getSheetByName(SHEET_MONTHLY);
  if (!monthly) {
    monthly = ss.insertSheet(SHEET_MONTHLY);
  } else {
    monthly.clear();
  }
  monthly.getRange("A1").setValue("Month");
  monthly.getRange("B1").setValue("Type");
  monthly.getRange("C1").setValue("Total Amount");
  monthly.getRange("A2").setFormula("=QUERY({ARRAYFORMULA(DATE(YEAR(Transactions!B2:B),MONTH(Transactions!B2:B),1)),Transactions!A2:A,Transactions!E2:E},\"select Col1, Col2, sum(Col3) where Col3 is not null group by Col1, Col2 label sum(Col3) 'Total Amount'\",0)");
  monthly.setFrozenRows(1);
  monthly.getRange("A:A").setNumberFormat("yyyy-mm");

  // Create Account Balances sheet
  let balances = ss.getSheetByName(SHEET_BALANCES);
  if (!balances) {
    balances = ss.insertSheet(SHEET_BALANCES);
  } else {
    balances.clear();
  }
  balances.getRange("A1").setValue("Account");
  balances.getRange("B1").setValue("Balance");
  balances.getRange("A2").setFormula("=QUERY(Transactions!F2:L,\"select Col1, sum(Col7) where Col1 is not null group by Col1 label sum(Col7) 'Balance'\",0)");
  tx.setColumnWidths(1, 12, 140);
  tx.setColumnWidth(2, 110);
  tx.setColumnWidth(5, 110);
  tx.setColumnWidth(11, 130);
}

/**
 * Render the HTML form.
 */
function doGet() {
  return HtmlService.createHtmlOutputFromFile('index')
    .setTitle('Income & Expense Logger')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

/**
 * Returns meta options: types, categories, subcategories, accounts, payment methods.
 */
function getMeta() {
  const ss = SpreadsheetApp.getActive();
  const meta = ss.getSheetByName(SHEET_META);
  const last = meta.getLastRow();
  const typeCatSub = meta.getRange(2,1,last-1,3).getValues()
    .filter(r => r[0] && r[1]) // Type, Category
    .map(r => ({ type: r[0], category: r[1], subcategory: r[2] || '' }));

  const accounts = meta.getRange(2,5, meta.getRange("E:E").getValues().filter(String).length-1, 1).getValues().flat().filter(String);
  const payMethods = meta.getRange(2,6, meta.getRange("F:F").getValues().filter(String).length-1, 1).getValues().flat().filter(String);

  const types = Array.from(new Set(typeCatSub.map(o => o.type)));
  const categoriesByType = {};
  types.forEach(t => {
    categoriesByType[t] = Array.from(new Set(typeCatSub.filter(o => o.type===t).map(o => o.category))).sort();
  });

  const subcategoriesByTypeCat = {};
  typeCatSub.forEach(o => {
    const key = o.type + '|' + o.category;
    if (!subcategoriesByTypeCat[key]) subcategoriesByTypeCat[key] = new Set();
    if (o.subcategory) subcategoriesByTypeCat[key].add(o.subcategory);
  });
  const subObj = {};
  Object.keys(subcategoriesByTypeCat).forEach(k => subObj[k] = Array.from(subcategoriesByTypeCat[k]).sort());

  return {
    types,
    categoriesByType,
    subcategoriesByTypeCat: subObj,
    accounts,
    paymentMethods: payMethods
  };
}

/**
 * Append a transaction row to the Transactions sheet.
 * @param {Object} data - form payload
 */
function submitTransaction(data) {
  const ss = SpreadsheetApp.getActive();
  const sh = ss.getSheetByName(SHEET_TRANSACTIONS);
  if (!sh) throw new Error('Transactions sheet not found. Run setup() first.');

  // Basic sanitation
  const type = (data.type || '').toString().trim();
  const dt = data.date ? new Date(data.date) : new Date();
  const category = (data.category || '').toString().trim();
  const subcategory = (data.subcategory || '').toString().trim();
  const amount = parseFloat(data.amount || 0);
  const account = (data.account || '').toString().trim();
  const paymentMethod = (data.paymentMethod || '').toString().trim();
  const tags = (data.tags || '').toString().trim();
  const description = (data.description || '').toString().trim();
  const receiptUrl = (data.receiptUrl || '').toString().trim();
  const createdAt = new Date();

  if (!type || !category || !amount || isNaN(amount)) {
    throw new Error('Missing required fields: Type, Category, Amount.');
  }

  sh.appendRow([
    type,
    dt,
    category,
    subcategory,
    amount,
    account,
    paymentMethod,
    tags,
    description,
    receiptUrl,
    createdAt
  ]);

  return { ok: true };
}
