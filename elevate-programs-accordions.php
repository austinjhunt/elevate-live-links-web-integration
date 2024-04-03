<?php
/*
    Austin Hunt 
    3/11/2024 - Present
    Elevate Programs Accordions
    Utilities for fetching data from Ellucian Elevate Live Links and displaying that data 
    on continuing education pages. 
    Primary function is displayElevateProgramsAccordions which takes in a sectionsUrl,
    a JSON string of course streams, and an optional message to display when no courses are available.
    The function fetches program instances from Elevate Live Links and displays them in an accordion format.
    The function is called in the Cascade CMS velocity format 'elevate-progams-accordions' here: 
        https://cofc.cascadecms.com/entity/open.act?id=f1ae9711ac1e002e44ad3ee6fc8869b8&type=format 
    Content editor provides the id or code of one or more course streams. Each course stream 
    is a single accordion. The accordion title is the Course Stream title, fetched from the API. 
    Then, inside a course stream accordion, we're looping through the programs, and for each 
    program we're looping through instances of that program, and if the courseStream of 
    that instance matches the parent accordion course stream, we're displaying each section 
    of that instance as a separate card. Since each section has one or many tutorials 
    (actual classes with instructors and meeting times and start/end dates), I considered 
    listing out each tutorial inside the View Details modal for the section card, but 
    that is not included currently (assuming one tutorial per section).   
    This utility was built to integrate directly with the same Standard template already in use 
    for the new design, as determined by the aforementioned velocity format conditionally rendering 
    the program accordions if the Elevate - Course Streams component is selected
    when editing a Standard page. 
    This PHP file lives in Cascade CMS at https://cofc.cascadecms.com/entity/open.act?id=f1cb6ee7ac1e002e44ad3ee636ff433f&type=file
    Data model notes. 
    Each program object contains
        - details of the programs that match the criteria defined in the request.
        - a programInstances array (of programInstance objects). Every programInstance object contains data for the program instances of the parent program.
        - One of the properties defined for the programInstance object is programInstanceTitle, the value of which is the program instance description, as defined in the corresponding field on the Properties tab of the instance record.
        - A programInstance object can also contain a services array (of service objects). Every service object contains data for the online services enabled for the program instance.
    Each section object contains the following arrays:
        A fees array (of fee objects). Every fee object contains information about the fees defined for the section, such as the fee type and category.
        A documents array (of document objects). Every document object describes a document that is associated with the section.
        A roles array (of role objects). Every role object contains information about the user roles associated with the section.
        A tutorials array (of tutorial objects). In this context, the term tutorial means, and is synonymous with, a class, and every tutorial object contains information about the classes scheduled for the section.
    */
?>

<?php
/* UTILITY FUNCTIONS */
function displayProgramInstanceModal()
{
?>

    <!-- define single modal used by all program instances -->
    <div id="program-instance-modal" class="modal program-instance-modal">
        <div id="program-instance-modal--instance-object-id" style="display:none"></div>
        <div class="modal-summary">
            <div class="card-icon">
                <i class="brei-icon fa-regular fa-tag"></i>
                <span id="program-instance-modal--instance-program-instance-id"></span>
            </div>
        </div>
        <div style="display: flex; justify-content: left; align-items: center;padding-top: 1rem;">
            <h4 id="program-instance-modal--title"></h4>
        </div>
        <div class="card-icon">
            <i class="brei-icon fa-regular fa-credit-card"></i>
            <span id="program-instance-modal--fee"></span>
        </div>
        <div class="card-icon">
            <i class="brei-icon fa-regular fa-graduation-cap"></i>
            <span id="program-instance-modal--credits"></span>
        </div>
        <div style="margin-top: 1rem">
            <p><span class="light-grey-uppercase">Concise Summary</span></p>
            <p id="program-instance-modal--summary-brief"></p>
        </div>
        <div style="margin-top: 1rem">
            <p><span class="light-grey-uppercase">Detailed Summary</span></p>
            <p id="program-instance-modal--summary-long"></p>
        </div>
        <div><span class="light-grey-uppercase" id="program-instance-modal--sections-count"></span></div>
        <div style="margin-top: 1rem" id="program-instance-modal--sections-info"></div>
    </div>
<?php
}



function _fetchSectionsData($sectionsUrl)
{
    $curl = curl_init($sectionsUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($json, true);
    return $data;
}

function fetchFilteredProgramInstancesByCourseStream($sectionsUrl, $matchMethod, $matchValue)
{
    /*
    Add all program instances matching a course stream to an array and return that array.
    Args: 
    $sectionsUrl - Elevate Live Links API endpoint to pull sections data
    $matchMethod - 'code' or 'id' to indicate how to match the courseStream 
    $matchValue - either code or id of a course stream depending on $matchMethod
    */
    $data = _fetchSectionsData($sectionsUrl);
    $instances = [];
    foreach ($data['programs'] as $program) {
        foreach ($program['program']['programInstances'] as $instance) {
            if ($instance['programInstance']['courseStream'] && $instance['programInstance']['courseStream'][$matchMethod] === $matchValue) {
                $instances[] = $instance['programInstance'];
            }
        }
    }
    return $instances;
}
function friendlyDate($dateString)
{
    $date = new DateTime($dateString);
    return $date->format('m/d/Y');
}
function guidv4($data = null)
{
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);
    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
function programInstanceWaitlistFull($programInstance)
{
    return $programInstance['waitlistPlacesLeft'] <= 0;
}
function programInstanceFull($programInstance)
{
    return $programInstance['placesLeft'] <= 0;
}
function getProgramInstanceInstructionalMethod($instance)
{
    // instructional method indicates the methods or formats in which a program or course instance is delivered.
    // try to get instructional method from instance, if not available, get from program
    // if not available, return 'Unknown'
    // ALIAS: Course Format 
    if (isset($instance['instructionalMethod']) && $instance['instructionalMethod'] && $instance['instructionalMethod']['title']) {
        return $instance['instructionalMethod']['title'];
    } else {
        return 'Unknown';
    }
}
function getProgramInstanceEnrollmentClosed($programInstance)
{
    /* Determine if enrollment is closed for a program instance. Need to use 
    the services array on the instance. The services array contains all of the 
    online services that are available for an instance of a parent program. 
    For context, the following codes are supported:
        ONLINE_REG (Enroll and Pay)
        GRADUATION (Attend Graduation)
        CURR_BUILD (Build Curriculum)
        ONLINE_APP (Apply Online)
        ONLINE_PAYMENT (Pay Fees)
        RESERVE_PLACE (Confirm Place)
        ACCEPT_OFFER (Accept Offer)
        LOG_CASE (Submit Request)
        PLAN_CURR (Plan Curriculum)
        PV_ASSIST_ENROL (Assisted Enrollment)
    We are interested in the ONLINE_REG service. If the service is not available,
    we can fall back to the programInstance status code to determine if enrollment is closed.
    */
    $closed = false;
    $services = $programInstance['services'];
    // find service in array where $service['service']['code'] === 'ONLINE_REG' and $service['service']['title'] === 'Enroll and Pay'
    $onlineRegigstrationService = array_filter($services, function ($service) {
        return $service['service']['code'] === 'ONLINE_REG' && $service['service']['title'] === 'Enroll and Pay';
    });
    if (empty($onlineRegigstrationService)) {
        // $programInstance['status']['code'] . if open, value is 'CI_OPEN'
        $closed = $programInstance['status']['code'] != 'CI_OPEN';
    } else {
        // use endDate value from service: "endDate": "24-OCT-2024"
        $endDate =  isset($onlineRegigstrationService[0]['endDate']) ? $onlineRegigstrationService[0]['endDate'] : null;
        $startDate = isset($onlineRegigstrationService[0]['startDate']) ? $onlineRegigstrationService[0]['startDate'] : null;
        if ($endDate && $startDate) {
            $today = new DateTime();
            $endDate = new DateTime($endDate);
            $startDate = new DateTime($startDate);
            $closed = $today > $endDate || $today < $startDate;
        }
    }
    return $closed;
}

function getFriendlyInstanceFee($instance)
{
    return $instance['fee'] > 0 ? '$' . number_format($instance['fee'], 2) : 'Free';
}


function displayProgramInstanceCard($instance)
{
    /* 
    IMPLEMENT INSTEAD OF SECTION CARD 
    Display all sections inside of program instance card.   Use section-specific fees for each section. Offer a bundle add to cart button that uses instance.fee.
    BUG FIX: if multiple program instances, not displaying all of them for some reason
    BUG FIX: allow single section associated to multiple program instances  
    */
    $showRegister = false; // default to false, set to true if enrollment is open and not full
    $showJoinWaitlist = false; // default to false, set to true if enrollment not open but waitlist is 

    // encode sections array to pass to js functions
    $encodedSectionsArray = isset($instance['sections']) && $instance['sections'] ?  json_encode($instance['sections']) : '[]';
    // escape double quotes from encoded string
    $encodedSectionsArray = str_replace('"', '\'', $encodedSectionsArray);
    // replace newlines 
    $encodedSectionsArray = str_replace(array("\n", "\r"), '', $encodedSectionsArray);
?>
    <div class="cell xsmall-12 medium-6" data-program-instance-object-id="<?php echo $instance['id'] ?>" data-program-instance-code="<?php echo $instance['code'] ?>" data-program-instance-id="<?php echo $instance['programInstanceID'] ?>">
        <div class="card-news" style="margin-bottom: 1rem; text-align: left;" itemscope="" itemtype="https://schema.org/NewsArticle">
            <div class="card-news__wrapper" style="padding-top: 0.75rem; padding-bottom: 0">
                <div class="card-news__content">
                    <div class="card-news__heading" style="display: flex; justify-content: space-between">
                        <span itemprop="headline"><strong><?php echo $instance['programInstanceTitle'] ?></strong></span>
                    </div>
                    <div class="card-icon">
                        <i class="brei-icon fa-light fa-credit-card"></i>
                        <span><?php echo getFriendlyInstanceFee($instance) ?></span>
                    </div>
                    <div class="card-icon">
                        <svg class="brei-icon brei-icon-tag" focusable="false">
                            <use href="#brei-icon-tag"></use>
                        </svg>
                        <span><?php echo $instance['programInstanceID'] ?></span>

                    </div>
                    <?php if (isset($instance['sections']) && count($instance['sections']) > 0) {
                        $sectionCount = count($instance['sections']); ?>
                        <div class="card-icon">
                            <svg class="brei-icon brei-icon-arrow" focusable="false">
                                <use href="#brei-icon-arrow"></use>
                            </svg>
                            <span>
                                <?php echo '' . $sectionCount . ' ' . ($sectionCount > 1 ? 'class sections' : 'class section') . ' included'  ?>
                            </span>
                        </div>
                    <?php } ?>

                    <?php if (getProgramInstanceEnrollmentClosed($instance)) { ?>
                        <div class="card-icon">
                            <svg class="brei-icon brei-icon-close" focusable="false">
                                <use href="#brei-icon-arrow"></use>
                            </svg>
                            <span>Enrollment is closed.</span>
                        </div>
                    <?php } else if (programInstanceFull($instance)) {
                    ?>
                        <div class="card-icon">
                            <svg class="brei-icon brei-icon-close" focusable="false">
                                <use href="#brei-icon-close"></use>
                            </svg>
                            <span>No seats available.</span>
                        </div>
                        <div class="card-icon">
                            <?php if (!programInstanceWaitlistFull($instance)) {
                                $showJoinWaitlist = true;
                            ?>
                                <svg class="brei-icon brei-icon-check" focusable="false">
                                    <use href="#brei-icon-check"></use>
                                </svg>
                                <span><?php echo $instance['waitlistPlacesLeft'] > 1 ? $instance['waitlistPlacesLeft'] . ' places ' : $instance['waitlistPlacesLeft'] . ' place ' ?>left on waitlist.</span>
                            <?php } else {  ?>
                                <svg class="brei-icon brei-icon-close" focusable="false">
                                    <use href="#brei-icon-close"></use>
                                </svg>
                                <span>Waitlist is also full.</span>
                            <?php
                            } ?>
                        </div>
                    <?php
                    } else {
                        $showRegister = true;
                    ?>
                        <div class="card-icon">
                            <svg class="brei-icon brei-icon-check" focusable="false">
                                <use href="#brei-icon-check"></use>
                            </svg>
                            <span>Course is open with <?php echo $instance['placesLeft'] ?> places left.</span>
                        </div>
                    <?php } ?>
                </div>
                <?php if ($showRegister) {
                ?>
                    <button data-add-program-instance-to-cart-instance-id="<?php echo $instance['id'] ?>" onclick="cart.addProgramInstance({ 
                         instanceObjectID: '<?php echo $instance['id'] ?>',
                        instanceProgramInstanceID: '<?php echo $instance['programInstanceID'] ?>',
                        instanceTitle: '<?php echo $instance['programInstanceTitle'] ?>',
                        instanceFee: <?php echo $instance['fee'] ?>,  
                        instanceSectionsJSON: <?php echo $encodedSectionsArray ?>, 
                        addedToWaitlist: false
                    })" class="card-news__button btn btn-card-bottom btn-card-bottom-left add-to-cart-btn">Add to Cart (Register)</button>
                <?php
                } else if ($showJoinWaitlist) {
                    /* join waitlist will also add to cart but will not charge. From Megan Libert 3/14 on Teams: 
                    In the regular Elevate student enrollment page, it allows them to go through the registration 
                    and then tells them they will be added to the waistlist and does not charge them any fees, 
                    so I imagine it would work in a similar way.  
                    This should be tested.
                    Passing a fee of 0 to the cart.addProgramInstance function. 
                    */
                ?>
                    <button data-add-program-instance-to-cart-instance-id="<?php echo $instance['id'] ?>" onclick="cart.addProgramInstance({
                        instanceObjectID: '<?php echo $instance['id'] ?>',
                        instanceProgramInstanceID: '<?php echo $instance['programInstanceID'] ?>',
                        instanceTitle: '<?php echo $instance['programInstanceTitle'] ?>',
                        instanceFee: 0,  
                        instanceSectionsJSON: <?php echo $encodedSectionsArray ?>, 
                        addedToWaitlist: true
                    })" class="card-news__button btn btn-card-bottom btn-card-bottom-left join-waitlist-btn">Add to Cart (Join Waitlist)</button>
                <?php
                }


                ?>
                <button onclick="showProgramInstanceDetailsModal({
                    instanceObjectID: '<?php echo $instance['id'] ?>',
                    instanceProgramInstanceID: '<?php echo $instance['programInstanceID'] ?>',
                    instanceFee: '<?php echo getFriendlyInstanceFee($instance) ?>',
                    instanceCredits: '<?php echo $instance['credits'] ?>',
                    instanceTitle: '<?php echo $instance['programInstanceTitle'] ?>',
                    instanceSummaryBrief: '<?php echo isset($instance['programInstanceSummaryBrief']) ? $instance['programInstanceSummaryBrief'] : '' ?>',
                    instanceSummaryLong: '<?php echo isset($instance['programInstanceSummaryLong']) ? $instance['programInstanceSummaryLong'] : '' ?>',
                    instanceSectionsJSON: <?php echo $encodedSectionsArray ?>, 
                })" title="Read more about Charleston Faces Stony Brook in CAA Finals Tonight" class="card-news__button btn btn-card-bottom btn-card-bottom-right ">
                    <span class="text">View Details</span>
                </button>

            </div>
        </div>
    </div>
<?php
}
function displayProgramInstancesGrid($courseStreamProgramInstances)
{
    // display all program instances in a grid - each instance is a card
?>
    <div class="news-content__cards grid-x grid-margin-x grid-margin-y" style="align-items: stretch">
        <?php
        foreach ($courseStreamProgramInstances as $programInstance) {
            displayProgramInstanceCard($programInstance);
        }
        ?>
    </div>
<?php
}

function getCourseStreamProperty($courseStreamProgramInstances, $property = 'title')
{
    // Args
    // $courseStreamProgramInstances - array of program instances from a course stream
    // $property - the property to get from the course stream, default is 'title', other options are 'id' and 'code'
    return $courseStreamProgramInstances[0]['courseStream'][$property];
}

?>

<style>
    /* make 90vw on mobile, 60vw on desktop */
    .modal.program-instance-modal {
        max-width: 95vw;
    }

    @media screen and (min-width: 768px) {
        .modal.program-instance-modal {
            max-width: 65vw;
        }
    }

    .accordions__content-with-padding {
        padding: 2rem 1.5rem 2rem 1.5rem;
    }

    .modal.program-instance-modal .card-icon {
        margin-top: 0.75rem;
    }

    .modal.program-instance-modal svg.brei-icon {
        width: 30px;
    }

    .card-news {
        cursor: inherit;
        height: 100%;
    }

    button.btn-card-bottom {
        font-size: 0.75rem;
        bottom: 0;
        position: absolute;
        -webkit-box-pack: center;
        -ms-flex-pack: center;
        background-color: #79242f;
        color: #fefefe;
        isolation: isolate;
        justify-content: center;
        margin-left: auto;
        margin-bottom: 0;
        padding-left: 0.125rem;
        position: relative;
        -webkit-transition: all 0.25s ease;
        transition: all 0.25s ease;
        max-width: 100%;
        min-width: fit-content;
        -webkit-box-align: center;
        -ms-flex-align: center;
        align-items: center;
        display: -webkit-box;
        display: -ms-flexbox;
        display: flex;
        padding: 1rem;
        cursor: pointer;
    }

    button.btn-card-bottom-right {
        right: 0;
        border-bottom-right-radius: 8px;
        border-top-left-radius: 20px;
    }

    button.btn-card-bottom-left {
        right: auto !important;
        left: 0 !important;
        border-bottom-left-radius: 8px;
        border-top-right-radius: 20px;
    }

    .btn {
        -webkit-font-smoothing: antialiased;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        display: inline-block;
        position: relative;
        -webkit-transform: translateZ(0);
        transform: translateZ(0);
        -webkit-transform-style: preserve-3d;
        transform-style: preserve-3d;
    }

    .modal.program-instance-modal p.card-icon {
        display: flex;
        align-items: center;
        justify-content: left;
        gap: 1rem;
    }

    .add-to-cart-btn[disabled],
    .join-waitlist-btn[disabled] {
        background-color: #ccc;
        cursor: not-allowed;
    }

    #cart-summary {
        text-align: center;
        display: flex;
        flex-direction: column;
    }

    #cart-total-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1rem;
    }

    #cart-header {
        background: rgb(225, 225, 225);
        width: 100%;
        padding: 5px 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #e1e1e1;
    }

    #cart-wrapper #cart-header h4 {
        color: #000;
        font-weight: normal;
    }

    #cart-close,
    .cart-close,
    .empty-cart {
        color: rgb(102, 102, 102);
        font-weight: normal;
        text-align: center;
        float: left;
        margin: 4px 0px 0px;
        font-style: normal;
        cursor: pointer;
    }

    /* shopping cart styles */
    #my-cart-toggle-button {
        padding: 10px 5px 0 10px;
        position: fixed;
        bottom: 0;
        left: 0;
        background-color: #fff;
        z-index: 999;
        box-shadow: 0 4px 8px 0 rgba(0, 0, 0, .2), 0 6px 20px 0 rgba(0, 0, 0, .19);
        cursor: pointer;
        font-size: .8em;
        display: flex;
        align-items: center;
        justify-content: left;
    }

    #my-cart-toggle-button h3 {
        cursor: pointer;
    }

    .cart-sum {
        padding: 3px 6px;
        font-weight: bold;
        text-align: center;
        margin-left: 1rem;
        background: red;
        color: #fff;
        -webkit-border-radius: 10px;
        -moz-border-radius: 10px;
        border-radius: 10px;
    }

    #cart-wrapper {
        width: 0px;
        opacity: 0;
        padding-right: 0px;
        position: fixed;
        top: 0px;
        left: 0px;
        background: rgb(255, 255, 255);
        font-size: 0.9em;
        z-index: 400;
        height: 100%;
        box-shadow: rgb(201, 201, 201) 0px 1px 9px 0px;
        -webkit-box-shadow: 0px 1px 9px 0px rgba(201, 201, 201, 1);
        -moz-box-shadow: 0px 1px 9px 0px rgba(201, 201, 201, 1);
        padding-right: 0px;
        overflow-x: hidden;
        transition: all 0.5s ease;
    }

    #cart-wrapper.open {
        width: 90%;
        -webkit-transition: all 0.5s ease;
        transition: all 0.5s ease;
        opacity: 1;
    }

    @media screen and (min-width: 992px) {
        #cart-wrapper.open {
            width: 40%;
        }
    }

    #cart-wrapper h3 {
        float: left;
        margin: 25px 0 5px;
        width: 100%;
        text-align: center;
        font-size: 1.5em;
        font-weight: 500;
    }

    #cart-wrapper #cart-close {
        color: #666;
        font-weight: normal;
    }

    #cart-items {
        padding: 0;
        float: left;
        margin: 5px 10px 10px 10px;
        width: calc(100% - 20px);
    }

    #cart-items .removeItem {
        text-decoration: underline;
        color: #000;
        font-weight: normal;
    }

    #cart-items li {
        list-style: none;
        margin: 0;
        width: 100%;
        border-bottom: 1px solid #eee;
        padding: 10px 0 10px;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    #cart-items li .flex-summary {
        display: flex;
        justify-content: space-evenly;
        align-items: center;
        width: 100%;
        flex-wrap: wrap;
    }

    #cart-items li .flex-summary p {
        margin-bottom: 0.25rem;
    }


    #cart-items li a {
        float: right;
    }

    #cart-items li b {
        float: left;
        width: auto;
        font-weight: normal;
    }

    #cart-items li .date {
        float: left;
        clear: both;
        width: calc(100% - 120px);
        margin-top: 10px;
        font-size: .9em;
        color: #000;
    }

    #cart-items li .price {
        float: right;
        margin-top: 10px;
        font-size: .9em;
        color: #000;
        width: 70px;
        text-align: right;
        font-weight: bold;
    }

    #cart-items li .cart-item-details {
        /* collapsed by default, expands when .open added */
        transition: all 0.5s ease;
        background: #f9f9f9;
        text-align: left;
        overflow: hidden;
        border-bottom-color: #eee;
        height: 0;
        padding: 0;
        border-width: 0;
    }

    #cart-return,
    #empty-cart {
        color: #000;
        margin-top: 20px;
        text-decoration: underline;
    }

    #cart-wrapper .cart-remove,
    .cart-view-item-details {
        color: #660000;
        cursor: pointer;
    }

    #cart-wrapper .card-icon {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.25rem;
    }

    .program-instance-modal .modal-summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .program-instance-modal .card-icon {
        align-items: center;
        display: flex;
        gap: 1rem;
    }

    .program-instance-modal .sections-list {
        list-style: none;
        margin-left: 0;
    }

    .program-instance-modal .sections-list li {
        margin-bottom: 0.5rem;
        margin-top: 0.5rem;
        border: 1px solid #ccc;
        padding: 0.5rem;
        border-radius: 1rem;
    }

    .light-grey-uppercase {
        color: #666;
        font-size: 0.8rem;
        text-transform: uppercase;
    }
</style>
<?php


displayProgramInstanceModal();

function displayElevateProgramsAccordions($sectionsUrl, $elevateCourseStreams, $noCoursesMessage = 'There are no course offerings at this time. Please check back later.')
{
    // passed in as a string, convert to array
    $elevateCourseStreams = json_decode($elevateCourseStreams, true);
?>
    <ul class="accordions__accordion accordion" data-accordion="data-accordion" data-deep-link="true" data-multi-expand="true" data-allow-all-closed="true">
        <?php
        // generate random id for component 
        $compId = guidv4();
        $indexCounter = 0;
        foreach ($elevateCourseStreams as $acc) {
            $index = $compId . '-' . $indexCounter;
            $matchMethod = $acc['match-method'];
            $matchValue = $acc['course-stream-' . $matchMethod];
            $matchValue =  is_int($matchValue) ? strval($matchValue) : $matchValue;
            $instances = fetchFilteredProgramInstancesByCourseStream($sectionsUrl, $matchMethod, $matchValue);
            $courseStreamTitle = getCourseStreamProperty($instances, 'title');
            $courseStreamCode = getCourseStreamProperty($instances, 'code');
            $courseStreamID = getCourseStreamProperty($instances, 'id');
        ?>
            <li data-course-stream-id="<?php echo $courseStreamID ?>" data-course-stream-code="<?php echo $courseStreamCode ?>" class="accordions__item accordion-item" data-accordion-item="data-accordion-item">
                <a href="#accordion-<?php echo $index ?>" class="accordions__heading accordion-title">
                    <span class="accordions__label font-h4"><?php echo $courseStreamTitle ?></span>
                    <span class="trigger">
                        <svg class="brei-icon brei-icon-plus" focusable="false">
                            <use href="#brei-icon-plus"></use>
                        </svg>
                        <svg class="brei-icon brei-icon-minus" focusable="false">
                            <use href="#brei-icon-minus"></use>
                        </svg>
                    </span>
                </a>
                <div class="accordions__content accordion-content accordions__content-with-padding" data-tab-content="data-tab-content" id="accordion-<?php echo $index ?>">
                    <?php
                    if (empty($instances)) {
                    ?>
                        <p><?php echo $noCoursesMessage ?></p>
                    <?php
                    } else {
                        displayProgramInstancesGrid($instances);
                    } ?>
                </div>
            </li>
        <?php } // end foreach 
        ?>
    </ul>
<?php } // end function 
?>
<div id="cart-wrapper">
    <div id="cart-summary">
        <div id="cart-header">
            <h1 id="cart-close" class="cart-close">X</h1>
            <h4>My Cart<span class="cart-sum">0</span></h4>
        </div>
        <ul id="cart-items"></ul>
        <div class="cart-total-wrapper">
            <span>Cart Total: </span>
            <span id="checkout-sum" class="text"></span>
        </div>
        <div style="display: flex; justify-content: center; align-items: center; padding: 1rem;">
            <a id="cart-checkout" class="btn btn--primary-small" href="#" target="_blank" alt="Checkout">Checkout</a>
        </div>
        <div style="display:flex; justify-content: space-evenly">
            <span id="cart-return" class="cart-close">Continue Shopping</span>
            <span id="empty-cart" class="empty-cart">Empty Cart</span>
        </div>
    </div>
</div>
<!-- SHOPPING CART SKELETON -->
<div id="my-cart-toggle-button">
    <h3 class="my-cart-header">My Cart</h3>
    <span class="cart-sum"></span>
</div>
<!-- END SHOPPING CART SKELETON -->