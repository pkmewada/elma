import fs from "node:fs/promises";
import path from "node:path";
import {
  AlignmentType,
  BorderStyle,
  Document,
  Footer,
  Header,
  HeadingLevel,
  LevelFormat,
  PageBreak,
  PageNumber,
  Packer,
  Paragraph,
  ShadingType,
  Table,
  TableCell,
  TableOfContents,
  TableRow,
  TextRun,
  VerticalAlign,
  WidthType,
} from "docx";

const root = nodeRepl.cwd;
const outputPath = path.join(root, "docs", "Modlus_Permission_Module_Client_Guide.docx");

const C = {
  navy: "1F4D78",
  blue: "2E74B5",
  ink: "192330",
  muted: "5F6978",
  lightBlue: "E8EEF5",
  lightGray: "F2F4F7",
  border: "C7D1DD",
  white: "FFFFFF",
};

const borders = {
  top: { style: BorderStyle.SINGLE, size: 4, color: C.border },
  bottom: { style: BorderStyle.SINGLE, size: 4, color: C.border },
  left: { style: BorderStyle.SINGLE, size: 4, color: C.border },
  right: { style: BorderStyle.SINGLE, size: 4, color: C.border },
  insideHorizontal: { style: BorderStyle.SINGLE, size: 4, color: C.border },
  insideVertical: { style: BorderStyle.SINGLE, size: 4, color: C.border },
};

const numberingConfigs = [
  {
    reference: "client-bullets",
    levels: [{
      level: 0,
      format: LevelFormat.BULLET,
      text: "•",
      alignment: AlignmentType.LEFT,
      style: {
        paragraph: { indent: { left: 540, hanging: 270 }, spacing: { after: 80, line: 300 } },
      },
    }],
  },
];

let numberedListCounter = 0;

const body = (text, options = {}) => new Paragraph({
  style: options.style || "Normal",
  alignment: options.alignment,
  spacing: { before: options.before ?? 0, after: options.after ?? 120, line: 300 },
  keepNext: options.keepNext,
  children: [new TextRun({
    text,
    bold: options.bold,
    italics: options.italics,
    size: options.size,
    color: options.color,
    font: "Calibri",
  })],
});

const heading = (text, level = 1) => new Paragraph({
  heading: level === 1 ? HeadingLevel.HEADING_1 : level === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
  keepNext: true,
  children: [new TextRun(text)],
});

const bullets = (items) => items.map((text) => new Paragraph({
  style: "Normal",
  numbering: { reference: "client-bullets", level: 0 },
  spacing: { after: 80, line: 300 },
  children: [new TextRun({ text, font: "Calibri" })],
}));

const numbered = (items) => {
  numberedListCounter += 1;
  const reference = `client-steps-${numberedListCounter}`;
  numberingConfigs.push({
    reference,
    levels: [{
      level: 0,
      format: LevelFormat.DECIMAL,
      text: "%1.",
      start: 1,
      alignment: AlignmentType.LEFT,
      style: {
        paragraph: { indent: { left: 720, hanging: 360 }, spacing: { after: 80, line: 300 } },
      },
    }],
  });

  return items.map((text) => new Paragraph({
    style: "Normal",
    numbering: { reference, level: 0 },
    spacing: { after: 80, line: 300 },
    children: [new TextRun({ text, font: "Calibri" })],
  }));
};

const callout = (label, text, fill = C.lightBlue) => [
  new Table({
    width: { size: 9360, type: WidthType.DXA },
    indent: { size: 120, type: WidthType.DXA },
    columnWidths: [9360],
    borders,
    rows: [new TableRow({ children: [new TableCell({
      width: { size: 9360, type: WidthType.DXA },
      shading: { fill, type: ShadingType.CLEAR },
      margins: { top: 100, bottom: 100, left: 120, right: 120 },
      verticalAlign: VerticalAlign.CENTER,
      children: [
        body(label, { bold: true, color: C.navy, after: 40 }),
        body(text, { size: 21, after: 0 }),
      ],
    })] })],
  }),
  body("", { after: 120 }),
];

const dataTable = (headers, rows, widths) => {
  const headerRow = new TableRow({
    tableHeader: true,
    children: headers.map((headerText, index) => new TableCell({
      width: { size: widths[index], type: WidthType.DXA },
      shading: { fill: C.lightBlue, type: ShadingType.CLEAR },
      margins: { top: 80, bottom: 80, left: 120, right: 120 },
      verticalAlign: VerticalAlign.CENTER,
      children: [body(headerText, { bold: true, color: C.navy, size: 20, after: 0 })],
    })),
  });

  const bodyRows = rows.map((row, rowIndex) => new TableRow({
    children: row.map((value, index) => new TableCell({
      width: { size: widths[index], type: WidthType.DXA },
      shading: rowIndex % 2 ? { fill: C.lightGray, type: ShadingType.CLEAR } : undefined,
      margins: { top: 80, bottom: 80, left: 120, right: 120 },
      verticalAlign: VerticalAlign.CENTER,
      children: [body(String(value), { size: 20, after: 0 })],
    })),
  }));

  return [
    new Table({
      width: { size: 9360, type: WidthType.DXA },
      indent: { size: 120, type: WidthType.DXA },
      columnWidths: widths,
      borders,
      rows: [headerRow, ...bodyRows],
    }),
    body("", { after: 120 }),
  ];
};

const children = [];

// Cover
children.push(body("", { after: 1200 }));
children.push(body("CLIENT OPERATIONS GUIDE", { alignment: AlignmentType.CENTER, bold: true, size: 21, color: C.blue, after: 280 }));
children.push(body("Modlus Permission Module", { alignment: AlignmentType.CENTER, bold: true, size: 56, color: C.navy, after: 200 }));
children.push(body("Page Access, Employee Exceptions, Button Actions and API Protection", { alignment: AlignmentType.CENTER, size: 28, color: C.muted, after: 480 }));
children.push(body("Prepared for authorized client administrators", { alignment: AlignmentType.CENTER, italics: true, size: 21, color: C.muted, after: 120 }));
children.push(body("Version 1.0  |  June 2026", { alignment: AlignmentType.CENTER, bold: true, size: 21, color: C.blue, after: 720 }));
children.push(...callout("Purpose", "Use this guide to give or restrict page and button access without editing application code. Follow the role-first process and use employee exceptions only when an individual needs different access."));
children.push(new Paragraph({ children: [new PageBreak()] }));

// TOC
children.push(heading("Contents", 1));
children.push(new TableOfContents("", { hyperlink: true, headingStyleRange: "1-3" }));
children.push(new Paragraph({ children: [new PageBreak()] }));

children.push(heading("1. Permission Module at a Glance", 1));
children.push(body("The permission module controls what each role and employee can see and do. Access is resolved in a consistent order: employee custom permission first, then the employee role or designation default. If no permission exists, access is denied."));
children.push(...callout("Core rule", "Use Role Permissions for the normal company-wide setup. Use Employee Exceptions only when one employee must differ from the role default."));
children.push(heading("Permission types", 2));
children.push(...dataTable(["Permission", "What it controls"], [
  ["View", "Whether the page can be opened and shown in the permission-aware menu."],
  ["Add", "Standard create or add operations on the page."],
  ["Edit", "Standard update operations on existing records."],
  ["Delete", "Standard deletion operations."],
  ["Approve", "Standard approval or verification operations."],
  ["Button Action", "A specific operation such as Import Leads, Add Overtime, Assign Asset or Send Agreement."],
], [2160, 7200]));
children.push(heading("Who can manage permissions", 2));
children.push(...bullets([
  "Permission Setup should be available only to authorized administrators.",
  "Route / Page Setup and Button Action registration are Super Admin functions.",
  "Administrators have button access by default. Employee access follows role defaults and employee exceptions.",
  "Do not share Super Admin credentials with ordinary users.",
]));

children.push(heading("2. Quick Start: Give Access to a Role", 1));
children.push(...numbered([
  "Open Permission Setup from the Admin panel.",
  "Open the Role Permissions tab.",
  "Select the required role or designation, for example Web Developer.",
  "Choose Admin Pages or Employee Pages under Select Page Type.",
  "Select the relevant module, for example Employee Panel.",
  "Find the required page and select View plus any required page actions.",
  "Enable or disable any listed Special Actions.",
  "Select Save Permissions.",
  "Ask the employee to reload the page and test the operation.",
]));
children.push(...callout("Important", "If an employee has Custom Permission enabled for the same page or button action, that custom setting overrides the role. Turn Custom Permission off when the employee should follow the role default.", C.lightGray));

children.push(heading("3. Role Permissions", 1));
children.push(body("Role Permissions define the standard access for everyone with the selected designation. This is the preferred place to manage recurring access."));
children.push(heading("Configure page access", 2));
children.push(...numbered([
  "Open Permission Setup and select Role Permissions.",
  "Select the role, such as Sales Executive or Web Developer.",
  "Select the page type. Use Employee Pages for employee-panel routes.",
  "Select a module to reduce the list of pages.",
  "For each page, select View, Add, Edit, Delete and Approve as required.",
  "Select Save Permissions.",
]));
children.push(heading("Recommended role setup practice", 2));
children.push(...bullets([
  "Give only the permissions needed for normal job responsibilities.",
  "Always grant View before expecting a page action or button action to work.",
  "Use one role default for the team instead of repeating the same employee exceptions.",
  "Review role permissions whenever a designation changes responsibilities.",
]));

children.push(heading("4. Employee Exceptions", 1));
children.push(body("Employee Exceptions are for individual differences. A custom employee permission is final for that page or button action and does not automatically follow future role changes."));
children.push(heading("Give or restrict page access for one employee", 2));
children.push(...numbered([
  "Open Permission Setup and select Employee Exceptions.",
  "Select the employee and relevant module.",
  "Find the required page and turn on Custom Permission.",
  "Select the exact View, Add, Edit, Delete and Approve values required.",
  "Select Save Exceptions.",
]));
children.push(heading("Return an employee to the role default", 2));
children.push(...numbered([
  "Open the employee and page in Employee Exceptions.",
  "Turn off Custom Permission.",
  "Save Exceptions. The employee now follows the current role defaults.",
]));
children.push(heading("Button action exceptions", 2));
children.push(body("Button Permissions appear below the page when actions have been registered in Route / Page Setup. Leave Custom Permission off to inherit the role. Turn it on and select Allow Access to permit the employee, or leave Allow Access clear to deny the employee."));

children.push(heading("5. Register a Button Action", 1));
children.push(body("Register a button action when a specific operation needs independent access control. Standard page actions can continue using View, Add, Edit, Delete and Approve. Use a Button Action when separate control is useful."));
children.push(heading("Registration procedure", 2));
children.push(...numbered([
  "Open Route / Page Setup as Super Admin.",
  "Find the page in Registered Routes and select Edit.",
  "Scroll to Button Actions.",
  "Enter the Button Label exactly as it appears to the user.",
  "Enter a permanent Action Key using lowercase letters, numbers and underscores.",
  "Optionally enter a Button Selector. Leave it blank for exact visible-label matching.",
  "Optionally enter the protected API endpoint and HTTP method.",
  "Keep Active selected, choose a Sort Order and select Add Button Action.",
  "Open Permission Setup and assign the new action to roles or employees.",
]));
children.push(heading("Button Action fields", 2));
children.push(...dataTable(["Field", "How to use it"], [
  ["Button Label", "Client-facing text such as Add Overtime. With no selector, matching is exact but ignores letter case and repeated spaces."],
  ["Action Key", "Permanent internal identifier such as add_overtime. It is not a CSS class and cannot be changed after creation."],
  ["Button Selector", "Optional CSS selector such as #addOvertimeBtn or .assign-asset-btn. Use it when labels repeat or a button has no text."],
  ["API Endpoint", "Optional path such as /api/emp-addOvertime.php. Mapping activates the central backend permission gate."],
  ["HTTP Method", "GET, POST, PUT, PATCH, DELETE or ANY."],
  ["Active", "When cleared, the action is removed from active permission use."],
  ["Sort Order", "Controls display order in permission screens."],
], [2160, 7200]));
children.push(...callout("Action Key rule", "The Action Key is a stable system identifier. Good: assign_asset. Avoid spaces, display labels or employee names.", C.lightGray));

children.push(heading("6. Central Button and API Protection", 1));
children.push(body("The system applies two coordinated controls. The shared frontend controller disables denied buttons. The central API gateway blocks mapped backend requests with HTTP 403. Both controls use the same route and Action Key."));
children.push(heading("Frontend button matching", 2));
children.push(...bullets([
  "If Button Selector is provided, the controller uses that selector.",
  "If Button Selector is blank, the controller looks for the exact visible Button Label.",
  "Denied controls are disabled and cannot open their modal or execute their click action.",
  "Dynamically added buttons are checked automatically.",
  "Allowed controls are left unchanged; existing business rules can still disable them.",
]));
children.push(heading("Backend API enforcement", 2));
children.push(...bullets([
  "Only endpoint and HTTP method pairs registered in Route Setup receive the action check.",
  "The user must have View access to the parent page and access to the Button Action.",
  "Denied requests receive HTTP 403 and do not reach the original API operation.",
  "Unmapped APIs continue through their existing logic.",
  "Choose the exact method used by the page. POST is normally used for create or update operations.",
]));

children.push(heading("7. Worked Example: Add Overtime for Web Developer", 1));
children.push(body("This example gives the Web Developer role access to Add Overtime on the Employee Overtime page, while allowing individual exceptions when required."));
children.push(heading("Step A - Confirm the registered action", 2));
children.push(...dataTable(["Setting", "Value"], [
  ["Route", "/emp-overtime-management"],
  ["Button Label", "Add Overtime"],
  ["Action Key", "add_overtime"],
  ["Button Selector", "Blank - exact label matching"],
  ["API Endpoint", "/api/emp-addOvertime.php"],
  ["HTTP Method", "POST"],
  ["Active", "Yes"],
], [2700, 6660]));
children.push(heading("Step B - Allow the role", 2));
children.push(...numbered([
  "Open Permission Setup and select Role Permissions.",
  "Select Web Developer, Employee Pages and Employee Panel.",
  "Find Employee Overtime and confirm View is selected.",
  "Under Special Actions, select Add Overtime.",
  "Select Save Permissions.",
]));
children.push(heading("Step C - Confirm Praveen follows the role", 2));
children.push(...numbered([
  "Open Employee Exceptions and select Praveen Mewada - Web Developer.",
  "Select Employee Panel and find Employee Overtime.",
  "Keep the Add Overtime Custom Permission switched off.",
  "Save Exceptions if a previous custom setting was removed.",
]));
children.push(heading("Step D - Test both outcomes", 2));
children.push(...numbered([
  "Sign in through Candidate Login using the employee account.",
  "Open Employee Overtime and confirm Add Overtime is available.",
  "Clear Add Overtime for Web Developer in Role Permissions and save.",
  "Reload the employee page and confirm the button is disabled.",
  "Confirm a denied direct API request receives HTTP 403.",
  "Restore the final required permission and test once more.",
]));

children.push(heading("8. Common Client Scenarios", 1));
children.push(...dataTable(["Requirement", "Correct operation"], [
  ["Allow a whole team", "Change Role Permissions for the designation."],
  ["Deny a whole team", "Clear the role page action or Special Action."],
  ["Allow one employee only", "Keep the role denied, then add an employee Custom Permission with Allow Access."],
  ["Deny one employee only", "Keep the role allowed, then add an employee Custom Permission with Allow Access clear."],
  ["Return employee to team rules", "Turn off Custom Permission and save."],
  ["Temporarily disable an action", "Edit the action in Route Setup and clear Active."],
  ["Button label changed", "Update Button Label when label matching is used, or use a stable Button Selector."],
  ["Protect a new API", "Add its /api/file.php endpoint and correct HTTP method to the Button Action."],
], [3000, 6360]));

children.push(heading("9. Troubleshooting", 1));
children.push(heading("The page redirects to Permission Denied", 2));
children.push(...bullets([
  "Confirm the correct employee account is being used and View is enabled.",
  "Check whether an employee Custom Permission overrides the role.",
  "Confirm the route is Active and assigned to the correct page type and module.",
  "Log out and sign in again if the browser was previously used for an Admin session.",
]));
children.push(heading("The button remains clickable when denied", 2));
children.push(...bullets([
  "Confirm the Button Action is Active and registered under the current route.",
  "With no selector, confirm Button Label exactly matches the visible button text.",
  "With a selector, confirm it uniquely identifies the button.",
  "Reload after saving permissions and remember that Admin button access is allowed by default.",
]));
children.push(heading("The API succeeds even though the button is denied", 2));
children.push(...bullets([
  "Confirm API Endpoint and HTTP Method are configured in Route Setup.",
  "Confirm the endpoint starts with /api/ and ends with .php.",
  "Confirm the selected method matches the actual browser request.",
  "Contact technical support if a correct mapping does not pass through the central gateway.",
]));
children.push(heading("The API returns 403 although access was allowed", 2));
children.push(...bullets([
  "Confirm View is allowed for the parent page.",
  "Check the employee Button Action Custom Permission.",
  "Confirm the employee designation matches the selected role.",
  "Confirm the endpoint and method map to the intended action.",
]));
children.push(heading("The employee does not follow new role changes", 2));
children.push(body("Open Employee Exceptions and turn off Custom Permission for the affected page or action. A saved custom row is final and intentionally overrides the role default."));

children.push(heading("10. Safe Operating Rules", 1));
children.push(...bullets([
  "Use role defaults first and employee exceptions second.",
  "Do not deactivate routes unless the page itself should stop operating.",
  "Do not change an Action Key after creation.",
  "Use a Button Selector when the same label appears more than once.",
  "Map only the API endpoint that performs the protected operation.",
  "Test both Allow and Deny after adding a new action.",
  "Keep Permission Setup and Route Setup restricted to authorized administrators.",
]));
children.push(...callout("Security reminder", "A disabled button improves the user experience. The mapped API endpoint is the security control that prevents direct requests. Configure both whenever an operation changes data.", C.lightGray));

children.push(heading("11. Client Handover Checklist", 1));
children.push(...bullets([
  "Authorized administrators can open Permission Setup.",
  "Only Super Admin users can open Route / Page Setup.",
  "Roles, employee designations and employee accounts are current.",
  "Important pages have View and standard actions configured.",
  "Important special buttons are registered as Button Actions.",
  "Data-changing actions have API endpoint and method mappings.",
  "Allow and Deny tests have been completed for each critical action.",
  "Obsolete employee exceptions have been removed.",
  "This guide is stored with the project handover documents.",
]));

children.push(heading("12. Glossary", 1));
children.push(...dataTable(["Term", "Meaning"], [
  ["Route", "Registered page path such as /emp-overtime-management."],
  ["Role Default", "Normal permission inherited from the employee designation."],
  ["Custom Permission", "Employee-specific value that replaces the role default."],
  ["Button Action", "A registered operation controlled independently."],
  ["Action Key", "Permanent internal permission identifier."],
  ["Button Selector", "Optional CSS selector used to find the exact frontend control."],
  ["API Endpoint", "Backend PHP path that performs an operation."],
  ["HTTP 403", "Response meaning the authenticated user is not permitted to perform the operation."],
  ["Central Gateway", "Shared backend layer that checks mapped API permissions before the original API runs."],
], [2400, 6960]));
children.push(body("End of guide", { alignment: AlignmentType.CENTER, italics: true, size: 18, color: C.muted, after: 0 }));

const header = new Header({
  children: [body("MODLUS  |  PERMISSION MODULE CLIENT GUIDE", { bold: true, size: 17, color: C.muted, after: 0 })],
});

const footer = new Footer({
  children: [new Paragraph({
    alignment: AlignmentType.RIGHT,
    children: [
      new TextRun({ text: "Modlus Permission Module  |  Client Operations Guide  |  ", size: 17, color: C.muted, font: "Calibri" }),
      new TextRun({ children: [PageNumber.CURRENT], size: 17, color: C.muted, font: "Calibri" }),
    ],
  })],
});

const doc = new Document({
  creator: "MQlus",
  title: "Modlus Permission Module - Client Operations Guide",
  subject: "Client guide for page, employee, button and API permissions",
  description: "Step-by-step operational guide for authorized Modlus administrators.",
  styles: {
    default: {
      document: {
        run: { font: "Calibri", size: 22, color: C.ink },
        paragraph: { spacing: { after: 120, line: 300 } },
      },
    },
    paragraphStyles: [
      {
        id: "Normal",
        name: "Normal",
        run: { font: "Calibri", size: 22, color: C.ink },
        paragraph: { spacing: { before: 0, after: 120, line: 300 } },
      },
      {
        id: "Heading1",
        name: "Heading 1",
        basedOn: "Normal",
        next: "Normal",
        quickFormat: true,
        run: { font: "Calibri", size: 32, bold: true, color: C.blue },
        paragraph: { spacing: { before: 360, after: 200 }, keepNext: true, outlineLevel: 0 },
      },
      {
        id: "Heading2",
        name: "Heading 2",
        basedOn: "Normal",
        next: "Normal",
        quickFormat: true,
        run: { font: "Calibri", size: 26, bold: true, color: C.blue },
        paragraph: { spacing: { before: 280, after: 140 }, keepNext: true, outlineLevel: 1 },
      },
      {
        id: "Heading3",
        name: "Heading 3",
        basedOn: "Normal",
        next: "Normal",
        quickFormat: true,
        run: { font: "Calibri", size: 24, bold: true, color: C.navy },
        paragraph: { spacing: { before: 200, after: 100 }, keepNext: true, outlineLevel: 2 },
      },
    ],
  },
  numbering: { config: numberingConfigs },
  sections: [{
    properties: {
      page: {
        size: { width: 12240, height: 15840 },
        margin: { top: 1440, right: 1440, bottom: 1440, left: 1440, header: 708, footer: 708 },
      },
    },
    headers: { default: header },
    footers: { default: footer },
    children,
  }],
});

await fs.mkdir(path.dirname(outputPath), { recursive: true });
const buffer = await Packer.toBuffer(doc);
await fs.writeFile(outputPath, buffer);
nodeRepl.write(JSON.stringify({ outputPath, bytes: buffer.length, sections: 12, tables: 8 }));
