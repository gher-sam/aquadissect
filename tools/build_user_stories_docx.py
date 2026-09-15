from __future__ import annotations

from datetime import datetime, timezone
from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_ALIGN_VERTICAL, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


OUTPUT = Path(__file__).resolve().parents[1] / "deliverables" / "GDLM-AIS-MO_User_Stories.docx"

NAVY = "0B2545"
BLUE = "2E74B5"
DARK_BLUE = "1F4D78"
MUTED = "5E6B78"
LIGHT_BLUE = "E8EEF5"
LIGHT_GRAY = "F2F4F7"
CALLOUT = "F4F6F9"
WHITE = "FFFFFF"
BLACK = "000000"
BORDER = "B8C2CC"
GREEN = "2F6B4F"
GOLD = "7A5A00"

CONTENT_WIDTH_DXA = 9360
TABLE_INDENT_DXA = 120
CELL_MARGINS = {"top": 80, "bottom": 80, "start": 120, "end": 120}


STORIES = {
    "Student": [
        {
            "id": "STU-01",
            "title": "Sign in securely",
            "priority": "Must",
            "story": "As a student, I want to sign in using my authorized account so that I can securely access my assigned learning activities and records.",
            "criteria": [
                "Given an active student account, when valid credentials are submitted, then the system opens the student dashboard.",
                "Given invalid credentials, when sign-in is attempted, then the system shows a clear generic error without revealing which credential is incorrect.",
                "Given a signed-in student, when a Faculty or Program Head function is requested, then access is denied and the event is logged.",
                "Given an active session, when the student signs out, then the session is ended and protected pages require sign-in again.",
            ],
        },
        {
            "id": "STU-02",
            "title": "View and select an approved organism module",
            "priority": "Must",
            "story": "As a student, I want to view available marine-organism modules so that I can select the learning activity assigned to me.",
            "criteria": [
                "The dashboard displays only modules that are published, available to the student, and within the approved organism scope.",
                "Each module displays its organism name, activity title, learning objective, availability status, and progress status.",
                "When a student opens an available module, the system loads its approved content and resumes the student's saved status when applicable.",
                "When a module is unavailable or fails to load, the system explains the condition and provides a safe next action.",
            ],
        },
        {
            "id": "STU-03",
            "title": "Explore anatomical structures",
            "priority": "Must",
            "story": "As a student, I want to inspect the approved anatomical model or visual reference so that I can understand the location and relationship of structures.",
            "criteria": [
                "The viewer presents only the approved model, image, labels, and descriptions for the selected organism.",
                "Where a 3D model is available, the student can rotate, zoom, select, and reset the view using clearly labeled controls.",
                "When a structure is selected, the system highlights it and displays its approved name and description.",
                "If a model or image cannot load, the system preserves the activity state and displays an understandable recovery message.",
            ],
        },
        {
            "id": "STU-04",
            "title": "Follow guided dissection steps",
            "priority": "Must",
            "story": "As a student, I want the dissection activity presented step by step so that I can follow the approved procedure in the correct sequence.",
            "criteria": [
                "Each step identifies the required action, structure to observe, safety or handling reminder when applicable, and expected response.",
                "The system clearly indicates the current step, completed steps, and remaining progress.",
                "When sequential completion is required, the next step remains unavailable until the current step meets its validation rule.",
                "The student can review completed instructional content without changing a submitted result unless revision is permitted by Faculty.",
            ],
        },
        {
            "id": "STU-05",
            "title": "Complete anatomical identification activities",
            "priority": "Must",
            "story": "As a student, I want to identify selected anatomical structures so that I can practice and demonstrate recognition of approved parts.",
            "criteria": [
                "Each identification item is linked to an approved label, answer key, and activity step.",
                "When the student submits a response, the system records the selected structure, result, attempt number, and timestamp.",
                "The activity is limited to approved structures and does not present its result as disease diagnosis, unrestricted species classification, or expert verification.",
                "When a response cannot be processed, the student can retry without losing previously saved activity data.",
            ],
        },
        {
            "id": "STU-06",
            "title": "Receive immediate instructional feedback",
            "priority": "Must",
            "story": "As a student, I want clear feedback on my identification responses so that I can correct misunderstandings while learning.",
            "criteria": [
                "A correct response produces a confirmation and the approved explanatory feedback.",
                "An incorrect response produces a constructive hint or correction according to the Faculty-approved attempt rule.",
                "Feedback identifies the learning issue without exposing restricted answer-key data beyond the configured level.",
                "All scored attempts and system feedback are saved for later Faculty review.",
            ],
        },
        {
            "id": "STU-07",
            "title": "Use the controlled AI learning assistant",
            "priority": "Should",
            "story": "As a student, I want to ask the AI learning assistant questions about the approved lesson so that I can receive simpler explanations and clarification.",
            "criteria": [
                "The assistant is limited to approved anatomical content, guided steps, and study-support purposes for the selected module.",
                "Every AI response is clearly labeled as assistance and not as final anatomical authority.",
                "When a request is outside scope or uncertain, the assistant states the limitation and directs the student to Faculty or approved references.",
                "Relevant AI interactions are retained according to the approved privacy and review policy and can be inspected by authorized Faculty.",
            ],
        },
        {
            "id": "STU-08",
            "title": "Save and resume learning progress",
            "priority": "Must",
            "story": "As a student, I want my activity progress saved so that I can continue an incomplete guided dissection without repeating completed work.",
            "criteria": [
                "The system saves completed steps, responses, feedback, observations, and timestamps after each confirmed action.",
                "When the student returns to an incomplete activity, the system resumes at the latest valid step and shows previously completed work.",
                "A student can view only their own progress and reports unless an authorized sharing process is provided.",
                "If saving fails, the system informs the student and avoids falsely marking the activity as saved or completed.",
            ],
        },
        {
            "id": "STU-09",
            "title": "Generate a laboratory activity report",
            "priority": "Must",
            "story": "As a student, I want to generate and download my laboratory activity report so that I can organize and submit evidence of my completed work.",
            "criteria": [
                "The report identifies the student, selected organism, activity title, completed steps, observations, identified structures, results, feedback, and relevant timestamps.",
                "The report is generated only from the student's saved activity record and approved anatomical content.",
                "The student can preview the report and use the supported print or download option without altering recorded results.",
                "A submitted report retains its activity version and review status for reliable Faculty checking.",
            ],
        },
    ],
    "Faculty": [
        {
            "id": "FAC-01",
            "title": "Access the instructional dashboard",
            "priority": "Must",
            "story": "As a Faculty user, I want an instructional dashboard so that I can access assigned modules, student records, and items requiring review.",
            "criteria": [
                "The dashboard shows only classes, modules, and student records assigned to or authorized for the Faculty user.",
                "The dashboard summarizes not started, in progress, completed, submitted, and needs-review activities.",
                "The Faculty user can filter the view by class, module, organism, activity status, and student.",
                "Student records cannot be viewed through an unassigned or unauthorized Faculty account.",
            ],
        },
        {
            "id": "FAC-02",
            "title": "Assign approved learning modules",
            "priority": "Must",
            "story": "As a Faculty user, I want to assign approved modules to my students so that the correct guided dissection activities are available for instruction.",
            "criteria": [
                "Only Program Head-approved and currently published modules are available for assignment.",
                "The Faculty user can select the authorized class, module, opening date, due date, and applicable instructions.",
                "Before saving, the system displays the assignment scope and asks for confirmation.",
                "Changes to an active assignment are logged and do not erase existing student work.",
            ],
        },
        {
            "id": "FAC-03",
            "title": "Monitor student progress",
            "priority": "Must",
            "story": "As a Faculty user, I want to monitor student progress so that I can identify learners who need guidance or have incomplete activities.",
            "criteria": [
                "The Faculty user can see each authorized student's current step, completion percentage, last activity time, and submission status.",
                "Selecting a student opens the saved step history, responses, attempts, feedback, and observations for the chosen activity.",
                "Progress summaries distinguish incomplete work from technical failures or activities awaiting review.",
                "Monitoring data are read-only unless the Faculty user performs a separately authorized review or feedback action.",
            ],
        },
        {
            "id": "FAC-04",
            "title": "Review and validate student outputs",
            "priority": "Must",
            "story": "As a Faculty user, I want to review identification results and laboratory reports so that anatomical correctness remains subject to instructor validation.",
            "criteria": [
                "The review view presents the student's response, approved answer, system feedback, AI assistance when relevant, observations, and generated report.",
                "The Faculty user can mark an output as accepted, needs revision, or corrected and must record a reason for a correction or override.",
                "A Faculty decision is stored with the reviewer, timestamp, original result, and revised status.",
                "The system does not silently replace the original student response or AI-generated suggestion.",
            ],
        },
        {
            "id": "FAC-05",
            "title": "Provide instructional feedback",
            "priority": "Must",
            "story": "As a Faculty user, I want to give feedback on student work so that learners know what to correct and how to improve.",
            "criteria": [
                "The Faculty user can attach feedback to the activity, a specific step, an identification response, or the laboratory report.",
                "Saved feedback identifies the author and timestamp and becomes visible only to the appropriate student and authorized reviewers.",
                "When revision is allowed, the system identifies the items to revise and preserves the original submission history.",
                "The student dashboard clearly indicates that Faculty feedback is available.",
            ],
        },
        {
            "id": "FAC-06",
            "title": "Propose and maintain instructional content",
            "priority": "Should",
            "story": "As a Faculty user, I want to prepare or revise instructional content so that the guided modules remain scientifically accurate and instructionally useful.",
            "criteria": [
                "The Faculty user can draft permitted organism descriptions, anatomical labels, guided steps, questions, answer keys, hints, and references.",
                "Required content fields and media-use information are validated before a draft can be submitted for approval.",
                "A revised item retains its previous published version and records the editor, date, and reason for change.",
                "Faculty drafts cannot become student-visible until the Program Head approves and publishes the applicable version.",
            ],
        },
    ],
    "Program Head": [
        {
            "id": "PH-01",
            "title": "Authorize users and role access",
            "priority": "Must",
            "story": "As the Program Head, I want to authorize user roles and access so that system functions and academic records are available only to appropriate users.",
            "criteria": [
                "The Program Head can approve, update, deactivate, or reactivate authorized Student and Faculty access within the permitted administrative scope.",
                "A user has only one approved role context at a time unless a documented multi-role assignment is authorized.",
                "Role changes take effect without deleting the user's historical academic records.",
                "Every access change records the affected account, action, responsible user, timestamp, and reason.",
            ],
        },
        {
            "id": "PH-02",
            "title": "Approve instructional and anatomical content",
            "priority": "Must",
            "story": "As the Program Head, I want to review and approve module content so that only validated learning materials are released to students.",
            "criteria": [
                "The approval view identifies the organism, content version, author or editor, anatomical labels, guided steps, answer keys, media provenance, and Faculty validation status.",
                "The Program Head can approve, return for revision, or reject a submission and must record an approval note or reason.",
                "Rejected or returned content remains unavailable to students and retains its review history.",
                "Approval does not represent unrestricted biological certification; it applies only to the project's selected organisms, structures, and instructional scope.",
            ],
        },
        {
            "id": "PH-03",
            "title": "Control module publication and availability",
            "priority": "Must",
            "story": "As the Program Head, I want to publish or withdraw approved modules so that students and Faculty use the correct instructional version.",
            "criteria": [
                "Only an approved module version can be published.",
                "Publication records the effective date, published version, responsible user, and intended access scope.",
                "Withdrawing a module prevents new activity starts but preserves completed and in-progress records according to the approved policy.",
                "Students and Faculty receive a clear status when a module is scheduled, active, closed, or withdrawn.",
            ],
        },
        {
            "id": "PH-04",
            "title": "Monitor program-level use and learning progress",
            "priority": "Should",
            "story": "As the Program Head, I want program-level summaries so that I can monitor implementation, completion, and areas needing academic support.",
            "criteria": [
                "The summary shows authorized aggregate counts for users, assigned activities, completion, submissions, pending reviews, and recurring problem areas.",
                "The Program Head can filter summaries by period, class, module, organism, Faculty assignment, and activity status.",
                "Aggregate information links to detailed records only when the Program Head is authorized to view those records.",
                "Viewing a summary does not allow the Program Head to silently change student responses or Faculty decisions.",
            ],
        },
        {
            "id": "PH-05",
            "title": "Review audit records and export oversight reports",
            "priority": "Must",
            "story": "As the Program Head, I want an auditable record and consolidated reports so that approvals, access changes, and academic use can be reviewed responsibly.",
            "criteria": [
                "The audit view records significant authentication, access, content, publication, validation, and report actions with actor and timestamp.",
                "Authorized filters can locate records by user, role, module, action type, and date range.",
                "The Program Head can export a readable oversight report containing only the fields required for the stated academic purpose.",
                "Audit records and exports are protected from unauthorized alteration and do not expose credentials or unnecessary sensitive information.",
            ],
        },
    ],
}


def set_run_font(run, name="Calibri", size=None, color=None, bold=None, italic=None):
    run.font.name = name
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:eastAsia"), name)
    if size is not None:
        run.font.size = Pt(size)
    if color is not None:
        run.font.color.rgb = RGBColor.from_string(color)
    if bold is not None:
        run.bold = bold
    if italic is not None:
        run.italic = italic


def set_repeat_header(row):
    tr_pr = row._tr.get_or_add_trPr()
    tbl_header = OxmlElement("w:tblHeader")
    tbl_header.set(qn("w:val"), "true")
    tr_pr.append(tbl_header)


def prevent_row_split(row):
    tr_pr = row._tr.get_or_add_trPr()
    cant_split = OxmlElement("w:cantSplit")
    tr_pr.append(cant_split)


def set_cell_shading(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def set_cell_margins(cell, margins=CELL_MARGINS):
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_mar = tc_pr.find(qn("w:tcMar"))
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for key, value in margins.items():
        tag = "start" if key == "start" else "end" if key == "end" else key
        node = tc_mar.find(qn(f"w:{tag}"))
        if node is None:
            node = OxmlElement(f"w:{tag}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def set_table_borders(table, color=BORDER, size=4, style="single"):
    tbl_pr = table._tbl.tblPr
    borders = tbl_pr.find(qn("w:tblBorders"))
    if borders is None:
        borders = OxmlElement("w:tblBorders")
        tbl_pr.append(borders)
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        elem = borders.find(qn(f"w:{edge}"))
        if elem is None:
            elem = OxmlElement(f"w:{edge}")
            borders.append(elem)
        elem.set(qn("w:val"), style)
        elem.set(qn("w:sz"), str(size))
        elem.set(qn("w:space"), "0")
        elem.set(qn("w:color"), color)


def set_table_geometry(table, widths, indent=TABLE_INDENT_DXA):
    assert sum(widths) == CONTENT_WIDTH_DXA, (widths, sum(widths))
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    table.autofit = False
    tbl_pr = table._tbl.tblPr
    layout = tbl_pr.find(qn("w:tblLayout"))
    if layout is None:
        layout = OxmlElement("w:tblLayout")
        tbl_pr.append(layout)
    layout.set(qn("w:type"), "fixed")
    tbl_w = tbl_pr.find(qn("w:tblW"))
    if tbl_w is None:
        tbl_w = OxmlElement("w:tblW")
        tbl_pr.append(tbl_w)
    tbl_w.set(qn("w:w"), str(CONTENT_WIDTH_DXA))
    tbl_w.set(qn("w:type"), "dxa")
    tbl_ind = tbl_pr.find(qn("w:tblInd"))
    if tbl_ind is None:
        tbl_ind = OxmlElement("w:tblInd")
        tbl_pr.append(tbl_ind)
    tbl_ind.set(qn("w:w"), str(indent))
    tbl_ind.set(qn("w:type"), "dxa")

    grid = table._tbl.tblGrid
    for child in list(grid):
        grid.remove(child)
    for width in widths:
        col = OxmlElement("w:gridCol")
        col.set(qn("w:w"), str(width))
        grid.append(col)

    for row in table.rows:
        prevent_row_split(row)
        for idx, cell in enumerate(row.cells):
            width = widths[idx]
            tc_pr = cell._tc.get_or_add_tcPr()
            tc_w = tc_pr.find(qn("w:tcW"))
            if tc_w is None:
                tc_w = OxmlElement("w:tcW")
                tc_pr.append(tc_w)
            tc_w.set(qn("w:w"), str(width))
            tc_w.set(qn("w:type"), "dxa")
            cell.width = Inches(width / 1440)
            cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
            set_cell_margins(cell)


def style_table_text(table, header=True, body_size=9.5):
    for row_idx, row in enumerate(table.rows):
        for cell in row.cells:
            for paragraph in cell.paragraphs:
                paragraph.paragraph_format.space_before = Pt(0)
                paragraph.paragraph_format.space_after = Pt(2)
                paragraph.paragraph_format.line_spacing = 1.05
                for run in paragraph.runs:
                    set_run_font(
                        run,
                        size=9.5 if row_idx == 0 and header else body_size,
                        color=WHITE if row_idx == 0 and header else BLACK,
                        bold=True if row_idx == 0 and header else None,
                    )
        if row_idx == 0 and header:
            for cell in row.cells:
                set_cell_shading(cell, NAVY)


def add_field(paragraph, instruction):
    run = paragraph.add_run()
    begin = OxmlElement("w:fldChar")
    begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = instruction
    separate = OxmlElement("w:fldChar")
    separate.set(qn("w:fldCharType"), "separate")
    text = OxmlElement("w:t")
    text.text = "1"
    end = OxmlElement("w:fldChar")
    end.set(qn("w:fldCharType"), "end")
    run._r.extend([begin, instr, separate, text, end])
    set_run_font(run, size=9, color=MUTED)


def set_paragraph_fill_and_border(paragraph, fill=CALLOUT, border_color=BLUE):
    p_pr = paragraph._p.get_or_add_pPr()
    shd = p_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        p_pr.append(shd)
    shd.set(qn("w:fill"), fill)
    borders = p_pr.find(qn("w:pBdr"))
    if borders is None:
        borders = OxmlElement("w:pBdr")
        p_pr.append(borders)
    left = borders.find(qn("w:left"))
    if left is None:
        left = OxmlElement("w:left")
        borders.append(left)
    left.set(qn("w:val"), "single")
    left.set(qn("w:sz"), "18")
    left.set(qn("w:space"), "8")
    left.set(qn("w:color"), border_color)


def create_bullet_numbering(doc):
    numbering = doc.part.numbering_part.element
    abstract_ids = [int(x.get(qn("w:abstractNumId"))) for x in numbering.findall(qn("w:abstractNum"))]
    num_ids = [int(x.get(qn("w:numId"))) for x in numbering.findall(qn("w:num"))]
    abstract_id = max(abstract_ids, default=0) + 1
    num_id = max(num_ids, default=0) + 1

    abstract = OxmlElement("w:abstractNum")
    abstract.set(qn("w:abstractNumId"), str(abstract_id))
    multi = OxmlElement("w:multiLevelType")
    multi.set(qn("w:val"), "singleLevel")
    abstract.append(multi)
    lvl = OxmlElement("w:lvl")
    lvl.set(qn("w:ilvl"), "0")
    start = OxmlElement("w:start")
    start.set(qn("w:val"), "1")
    fmt = OxmlElement("w:numFmt")
    fmt.set(qn("w:val"), "bullet")
    text = OxmlElement("w:lvlText")
    text.set(qn("w:val"), "•")
    jc = OxmlElement("w:lvlJc")
    jc.set(qn("w:val"), "left")
    p_pr = OxmlElement("w:pPr")
    tabs = OxmlElement("w:tabs")
    tab = OxmlElement("w:tab")
    tab.set(qn("w:val"), "num")
    tab.set(qn("w:pos"), "720")
    tabs.append(tab)
    ind = OxmlElement("w:ind")
    ind.set(qn("w:left"), "720")
    ind.set(qn("w:hanging"), "360")
    spacing = OxmlElement("w:spacing")
    spacing.set(qn("w:after"), "160")
    spacing.set(qn("w:line"), "280")
    spacing.set(qn("w:lineRule"), "auto")
    p_pr.extend([tabs, ind, spacing])
    r_pr = OxmlElement("w:rPr")
    fonts = OxmlElement("w:rFonts")
    fonts.set(qn("w:ascii"), "Calibri")
    fonts.set(qn("w:hAnsi"), "Calibri")
    r_pr.append(fonts)
    lvl.extend([start, fmt, text, jc, p_pr, r_pr])
    abstract.append(lvl)
    numbering.append(abstract)

    num = OxmlElement("w:num")
    num.set(qn("w:numId"), str(num_id))
    abstract_ref = OxmlElement("w:abstractNumId")
    abstract_ref.set(qn("w:val"), str(abstract_id))
    num.append(abstract_ref)
    numbering.append(num)
    return num_id


def add_bullet(doc, text, num_id, *, bold_prefix=None):
    p = doc.add_paragraph()
    p_pr = p._p.get_or_add_pPr()
    num_pr = OxmlElement("w:numPr")
    ilvl = OxmlElement("w:ilvl")
    ilvl.set(qn("w:val"), "0")
    num = OxmlElement("w:numId")
    num.set(qn("w:val"), str(num_id))
    num_pr.extend([ilvl, num])
    p_pr.append(num_pr)
    p.paragraph_format.space_after = Pt(8)
    p.paragraph_format.line_spacing = 1.167
    if bold_prefix and text.startswith(bold_prefix):
        r1 = p.add_run(bold_prefix)
        set_run_font(r1, bold=True)
        r2 = p.add_run(text[len(bold_prefix):])
        set_run_font(r2)
    else:
        set_run_font(p.add_run(text))
    return p


def set_style_font(style, name, size, color=BLACK, bold=None, italic=None):
    style.font.name = name
    style._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    style._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    style._element.get_or_add_rPr().rFonts.set(qn("w:eastAsia"), name)
    style.font.size = Pt(size)
    style.font.color.rgb = RGBColor.from_string(color)
    if bold is not None:
        style.font.bold = bold
    if italic is not None:
        style.font.italic = italic


def configure_document(doc):
    section = doc.sections[0]
    section.page_width = Inches(8.5)
    section.page_height = Inches(11)
    section.top_margin = Inches(1)
    section.right_margin = Inches(1)
    section.bottom_margin = Inches(1)
    section.left_margin = Inches(1)
    section.header_distance = Inches(0.492)
    section.footer_distance = Inches(0.492)
    section.different_first_page_header_footer = True

    styles = doc.styles
    normal = styles["Normal"]
    set_style_font(normal, "Calibri", 11)
    normal.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.LEFT
    normal.paragraph_format.space_before = Pt(0)
    normal.paragraph_format.space_after = Pt(6)
    normal.paragraph_format.line_spacing = 1.10

    title = styles["Title"]
    set_style_font(title, "Calibri", 28, NAVY, bold=True)
    title.paragraph_format.space_before = Pt(0)
    title.paragraph_format.space_after = Pt(8)
    title.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER

    subtitle = styles["Subtitle"]
    set_style_font(subtitle, "Calibri", 14, MUTED)
    subtitle.paragraph_format.space_after = Pt(12)
    subtitle.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER

    h1 = styles["Heading 1"]
    set_style_font(h1, "Calibri", 16, BLUE, bold=True)
    h1.paragraph_format.space_before = Pt(16)
    h1.paragraph_format.space_after = Pt(8)
    h1.paragraph_format.keep_with_next = True

    h2 = styles["Heading 2"]
    set_style_font(h2, "Calibri", 13, BLUE, bold=True)
    h2.paragraph_format.space_before = Pt(12)
    h2.paragraph_format.space_after = Pt(6)
    h2.paragraph_format.keep_with_next = True

    h3 = styles["Heading 3"]
    set_style_font(h3, "Calibri", 12, DARK_BLUE, bold=True)
    h3.paragraph_format.space_before = Pt(8)
    h3.paragraph_format.space_after = Pt(4)
    h3.paragraph_format.keep_with_next = True

    header = section.header
    hp = header.paragraphs[0]
    hp.alignment = WD_ALIGN_PARAGRAPH.LEFT
    hp.paragraph_format.space_after = Pt(0)
    r = hp.add_run("GDLM-AIS-MO  |  User Stories and Acceptance Criteria")
    set_run_font(r, size=9, color=MUTED, bold=True)

    footer = section.footer
    fp = footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    fp.paragraph_format.space_before = Pt(0)
    fp.paragraph_format.space_after = Pt(0)
    r = fp.add_run("Page ")
    set_run_font(r, size=9, color=MUTED)
    add_field(fp, "PAGE")
    r = fp.add_run(" of ")
    set_run_font(r, size=9, color=MUTED)
    add_field(fp, "NUMPAGES")


def add_cover(doc):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(48)
    r = p.add_run("SOUTHERN LEYTE STATE UNIVERSITY")
    set_run_font(r, size=11, color=MUTED, bold=True)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(14)
    r = p.add_run("CAPSTONE PROJECT DOCUMENTATION")
    set_run_font(r, size=10.5, color=BLUE, bold=True)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER

    p = doc.add_paragraph(style="Title")
    p.add_run("USER STORIES AND\nACCEPTANCE CRITERIA")

    p = doc.add_paragraph(style="Subtitle")
    p.add_run("Guided Dissection Learning Module and\nAnatomical Identification System for Marine Organisms")

    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(26)
    p.paragraph_format.space_after = Pt(6)
    r = p.add_run("USER ROLES")
    set_run_font(r, size=10, color=MUTED, bold=True)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(84)
    r = p.add_run("Student  |  Faculty  |  Program Head")
    set_run_font(r, size=13, color=NAVY, bold=True)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(4)
    r = p.add_run("Department of Information Technology")
    set_run_font(r, size=11, color=BLACK, bold=True)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(4)
    r = p.add_run("Version 1.0  |  2 September 2026")
    set_run_font(r, size=10.5, color=MUTED)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER

    p = doc.add_paragraph()
    r = p.add_run("Status: Ready for Submission")
    set_run_font(r, size=10.5, color=GREEN, bold=True)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    doc.add_page_break()


def add_document_control(doc):
    doc.add_heading("1. Document Overview", level=1)
    table = doc.add_table(rows=6, cols=2)
    rows = [
        ("Document title", "User Stories and Acceptance Criteria"),
        ("Project", "Guided Dissection Learning Module and Anatomical Identification System for Marine Organisms"),
        ("Version", "1.0"),
        ("Date", "2 September 2026"),
        ("Status", "Ready for Submission"),
        ("Source basis", "Capstone manuscript and its proposed functional scope"),
    ]
    for idx, (label, value) in enumerate(rows):
        table.cell(idx, 0).text = label
        table.cell(idx, 1).text = value
        set_cell_shading(table.cell(idx, 0), LIGHT_BLUE)
        for run in table.cell(idx, 0).paragraphs[0].runs:
            run.bold = True
            run.font.color.rgb = RGBColor.from_string(NAVY)
    set_table_geometry(table, [2700, 6660])
    set_table_borders(table)
    style_table_text(table, header=False, body_size=10)

    doc.add_heading("1.1 Purpose", level=2)
    p = doc.add_paragraph(
        "This document translates the proposed system scope into testable user stories for Student, Faculty, and Program Head users. "
        "It is intended to guide backlog planning, interface design, implementation, functional testing, and stakeholder acceptance."
    )
    p.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY

    doc.add_heading("1.2 Scope boundary", level=2)
    p = doc.add_paragraph(
        "The stories cover approved marine-organism learning modules, guided dissection procedures, anatomical visualization and identification, "
        "controlled AI-assisted explanations, progress monitoring, instructional review, content approval, and laboratory report generation. "
        "The system remains a supplemental educational tool and does not replace physical dissection, Faculty judgment, or expert biological validation."
    )
    p.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY

    p = doc.add_paragraph()
    set_paragraph_fill_and_border(p, fill=CALLOUT, border_color=GOLD)
    p.paragraph_format.left_indent = Inches(0.12)
    p.paragraph_format.right_indent = Inches(0.10)
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(10)
    r = p.add_run("Role interpretation. ")
    set_run_font(r, bold=True, color=GOLD)
    r = p.add_run(
        "Faculty is the instructional reviewer and subject-content validator. Program Head is the academic authorization, approval, publication, "
        "and oversight role. Technical server administration is outside this document unless separately assigned."
    )
    set_run_font(r)


def add_role_and_priority_overview(doc):
    doc.add_heading("2. Roles and Priorities", level=1)
    doc.add_heading("2.1 Role responsibilities", level=2)
    table = doc.add_table(rows=1, cols=3)
    headers = ["Role", "Primary objective", "Core responsibilities"]
    for i, value in enumerate(headers):
        table.cell(0, i).text = value
    role_rows = [
        ("Student", "Complete guided anatomy-learning activities", "Explore approved content; follow steps; identify structures; receive feedback; use controlled AI support; save progress; generate reports."),
        ("Faculty", "Guide, review, and validate learning", "Assign approved modules; monitor progress; validate results; review reports; provide feedback; propose instructional content."),
        ("Program Head", "Authorize and oversee academic use", "Authorize access; approve content; control publication; monitor program-level use; review audits and consolidated reports."),
    ]
    for role, objective, responsibilities in role_rows:
        cells = table.add_row().cells
        cells[0].text = role
        cells[1].text = objective
        cells[2].text = responsibilities
    set_repeat_header(table.rows[0])
    set_table_geometry(table, [1700, 2900, 4760])
    set_table_borders(table)
    style_table_text(table, body_size=9.5)

    doc.add_heading("2.2 Priority convention", level=2)
    table = doc.add_table(rows=1, cols=3)
    for i, value in enumerate(["Priority", "Meaning", "Release implication"]):
        table.cell(0, i).text = value
    for row in [
        ("Must", "Essential to the proposed workflow or its academic safeguards", "Required for the minimum acceptable prototype unless formally deferred."),
        ("Should", "High-value support or oversight capability", "Included when feasible; any deferral must be documented with its impact."),
    ]:
        cells = table.add_row().cells
        for i, value in enumerate(row):
            cells[i].text = value
    set_repeat_header(table.rows[0])
    set_table_geometry(table, [1500, 3100, 4760])
    set_table_borders(table)
    style_table_text(table, body_size=9.5)

    doc.add_heading("2.3 Acceptance-criteria convention", level=2)
    doc.add_paragraph(
        "Acceptance criteria describe observable conditions that must be demonstrated during testing. Where a criterion uses Given-When-Then wording, "
        "Given states the starting condition, When states the user action or event, and Then states the expected result."
    )


def add_backlog_summary(doc):
    doc.add_heading("3. Product Backlog Summary", level=1)
    doc.add_paragraph(
        "The following register provides a concise view of the twenty user stories. Detailed acceptance criteria follow in Sections 4 to 6."
    )
    table = doc.add_table(rows=1, cols=4)
    for i, value in enumerate(["Story ID", "Role", "Capability", "Priority"]):
        table.cell(0, i).text = value
    for role, stories in STORIES.items():
        for story in stories:
            cells = table.add_row().cells
            values = [story["id"], role, story["title"], story["priority"]
            ]
            for i, value in enumerate(values):
                cells[i].text = value
            if story["priority"] == "Must":
                set_cell_shading(cells[3], "E9F5EF")
            else:
                set_cell_shading(cells[3], "FFF8E8")
    set_repeat_header(table.rows[0])
    set_table_geometry(table, [1250, 1600, 5110, 1400])
    set_table_borders(table)
    style_table_text(table, body_size=9.2)
    for row in table.rows[1:]:
        row.cells[0].paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
        row.cells[3].paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER


def add_story_section(doc, role, section_number, num_id, page_break=False):
    if page_break:
        doc.add_page_break()
    doc.add_heading(f"{section_number}. {role} User Stories", level=1)
    intro = {
        "Student": "These stories describe how learners access, complete, and document approved guided-dissection activities while maintaining privacy and appropriate limits on AI assistance.",
        "Faculty": "These stories describe instructional assignment, monitoring, validation, feedback, and content-preparation responsibilities.",
        "Program Head": "These stories describe academic authorization, content approval, publication control, program oversight, and accountability responsibilities.",
    }[role]
    doc.add_paragraph(intro)

    for story in STORIES[role]:
        heading = doc.add_heading(f"{story['id']}  |  {story['title']}", level=2)
        heading.paragraph_format.keep_with_next = True

        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(5)
        p.paragraph_format.keep_with_next = True
        r = p.add_run("Priority: ")
        set_run_font(r, size=9.5, color=MUTED, bold=True)
        r = p.add_run(story["priority"])
        set_run_font(r, size=9.5, color=GREEN if story["priority"] == "Must" else GOLD, bold=True)

        p = doc.add_paragraph()
        set_paragraph_fill_and_border(p)
        p.paragraph_format.left_indent = Inches(0.12)
        p.paragraph_format.right_indent = Inches(0.10)
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after = Pt(7)
        p.paragraph_format.keep_with_next = True
        r = p.add_run(story["story"])
        set_run_font(r, size=11, color=NAVY, bold=True)

        label = doc.add_paragraph()
        label.paragraph_format.space_before = Pt(1)
        label.paragraph_format.space_after = Pt(3)
        label.paragraph_format.keep_with_next = True
        r = label.add_run("Acceptance criteria")
        set_run_font(r, size=10, color=DARK_BLUE, bold=True)
        for criterion in story["criteria"]:
            add_bullet(doc, criterion, num_id)


def add_business_rules(doc, num_id):
    doc.add_heading("7. Cross-Cutting Business Rules", level=1)
    rules = [
        ("BR-01 - Approved scope only. ", "Learning activities, anatomical labels, media, answers, and AI context must be limited to approved organisms and structures."),
        ("BR-02 - Human academic authority. ", "AI and automated identification outputs are advisory; Faculty validation and approved answer keys govern instructional correctness."),
        ("BR-03 - Role-based privacy. ", "Students access only their own records, while Faculty and Program Head access is limited to authorized academic responsibilities."),
        ("BR-04 - Traceable decisions. ", "Material access, content, publication, validation, and report actions must retain actor, timestamp, and the applicable version."),
        ("BR-05 - Preserve history. ", "Corrections, feedback, role changes, and module updates must not silently overwrite previous student work or approval history."),
        ("BR-06 - Safe failure. ", "Loading, saving, AI, and export failures must be explained clearly and must not falsely indicate success or destroy valid progress."),
        ("BR-07 - Supplemental use. ", "The system supports instruction but does not replace laboratory supervision, actual dissection, disease diagnosis, or expert biological verification."),
    ]
    for prefix, detail in rules:
        p = add_bullet(doc, prefix + detail, num_id, bold_prefix=prefix)
        p.paragraph_format.keep_together = True

    doc.add_heading("8. Definition of Done", level=1)
    items = [
        "The user story and every acceptance criterion have been implemented or formally deferred with documented approval.",
        "Functional tests demonstrate the expected result for normal, invalid, unauthorized, and recoverable failure conditions.",
        "Role-based access has been verified using Student, Faculty, and Program Head accounts.",
        "Approved anatomical content and answer keys have been reviewed by the designated subject-area Faculty.",
        "AI-assisted behavior is clearly labeled, bounded to approved content, and subject to human review.",
        "Saved progress, reports, feedback, version history, and audit records remain accurate after retesting.",
        "The interface provides clear instructions, readable feedback, keyboard-usable controls where applicable, and understandable error messages.",
        "The Product Owner or authorized academic representative has accepted the demonstrated result.",
    ]
    for item in items:
        add_bullet(doc, item, num_id)


def add_traceability(doc):
    doc.add_heading("9. Functional Traceability", level=1)
    doc.add_paragraph(
        "This matrix links the major proposed system functions to the user stories that define their expected behavior and acceptance."
    )
    table = doc.add_table(rows=1, cols=4)
    for i, value in enumerate(["System function", "Student stories", "Faculty stories", "Program Head stories"]):
        table.cell(0, i).text = value
    rows = [
        ("Authentication and role access", "STU-01", "FAC-01", "PH-01, PH-05"),
        ("Organism library and activity assignment", "STU-02", "FAC-02", "PH-02, PH-03"),
        ("Anatomical visualization", "STU-03", "FAC-04, FAC-06", "PH-02"),
        ("Guided dissection workflow", "STU-04", "FAC-02, FAC-03, FAC-06", "PH-02, PH-03"),
        ("Identification and instructional feedback", "STU-05, STU-06", "FAC-04, FAC-05", "PH-02, PH-04"),
        ("Controlled AI assistance", "STU-07", "FAC-04, FAC-06", "PH-02, PH-05"),
        ("Progress monitoring", "STU-08", "FAC-03", "PH-04"),
        ("Laboratory reports and review", "STU-09", "FAC-04, FAC-05", "PH-04, PH-05"),
        ("Content governance and publication", "-", "FAC-06", "PH-02, PH-03"),
    ]
    for row in rows:
        cells = table.add_row().cells
        for i, value in enumerate(row):
            cells[i].text = value
    set_repeat_header(table.rows[0])
    set_table_geometry(table, [3000, 1800, 2400, 2160])
    set_table_borders(table)
    style_table_text(table, body_size=9.2)

    doc.add_heading("10. Approval", level=1)
    doc.add_paragraph(
        "The undersigned confirm that these user stories appropriately describe the expected role-based behavior of the proposed system and may be used as the basis for implementation and acceptance testing."
    )

    table = doc.add_table(rows=3, cols=3)
    titles = ["Prepared by", "Reviewed by", "Approved by"]
    roles = ["Capstone Project Proponent(s)", "Faculty / Capstone Adviser", "Program Head"]
    for i in range(3):
        p = table.cell(0, i).paragraphs[0]
        p.paragraph_format.space_after = Pt(24)
        r = p.add_run(" ")
        set_run_font(r)
        p_pr = p._p.get_or_add_pPr()
        borders = OxmlElement("w:pBdr")
        bottom = OxmlElement("w:bottom")
        bottom.set(qn("w:val"), "single")
        bottom.set(qn("w:sz"), "6")
        bottom.set(qn("w:space"), "2")
        bottom.set(qn("w:color"), MUTED)
        borders.append(bottom)
        p_pr.append(borders)
        table.cell(1, i).text = titles[i]
        table.cell(2, i).text = roles[i]
        table.cell(1, i).paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
        table.cell(2, i).paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
        for run in table.cell(1, i).paragraphs[0].runs:
            set_run_font(run, size=10, bold=True, color=NAVY)
        for run in table.cell(2, i).paragraphs[0].runs:
            set_run_font(run, size=9, color=MUTED)
    set_table_geometry(table, [3120, 3120, 3120])
    set_table_borders(table, style="nil", size=0, color=WHITE)


def audit_document(doc):
    assert len(STORIES["Student"]) == 9
    assert len(STORIES["Faculty"]) == 6
    assert len(STORIES["Program Head"]) == 5
    assert sum(len(v) for v in STORIES.values()) == 20
    all_ids = [s["id"] for group in STORIES.values() for s in group]
    assert len(all_ids) == len(set(all_ids))
    for group in STORIES.values():
        for story in group:
            assert story["story"].startswith("As ")
            assert " I want " in story["story"]
            assert " so that " in story["story"]
            assert len(story["criteria"]) == 4
    for section in doc.sections:
        assert round(section.page_width.inches, 2) == 8.5
        assert round(section.page_height.inches, 2) == 11.0
        assert round(section.left_margin.inches, 2) == 1.0
        assert round(section.right_margin.inches, 2) == 1.0


def build():
    doc = Document()
    configure_document(doc)
    bullet_num_id = create_bullet_numbering(doc)

    props = doc.core_properties
    props.title = "User Stories and Acceptance Criteria - GDLM-AIS-MO"
    props.subject = "Student, Faculty, and Program Head user stories"
    props.author = "Capstone Project Proponents"
    props.last_modified_by = "Capstone Project Proponents"
    props.keywords = "user stories, acceptance criteria, guided dissection, anatomical identification, marine organisms"
    props.comments = "Prepared from the proposed system scope for capstone documentation."
    props.created = datetime(2026, 9, 2, tzinfo=timezone.utc)
    props.modified = datetime(2026, 9, 2, tzinfo=timezone.utc)

    add_cover(doc)
    add_document_control(doc)
    add_role_and_priority_overview(doc)
    add_backlog_summary(doc)
    add_story_section(doc, "Student", 4, bullet_num_id, page_break=False)
    add_story_section(doc, "Faculty", 5, bullet_num_id, page_break=False)
    add_story_section(doc, "Program Head", 6, bullet_num_id, page_break=False)
    add_business_rules(doc, bullet_num_id)
    add_traceability(doc)

    audit_document(doc)
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc.save(OUTPUT)
    print(f"CREATED={OUTPUT}")
    print(f"STORIES={sum(len(v) for v in STORIES.values())}")
    print(f"PARAGRAPHS={len(doc.paragraphs)}")
    print(f"TABLES={len(doc.tables)}")


if __name__ == "__main__":
    build()
