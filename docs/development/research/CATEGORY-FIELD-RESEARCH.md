# Category field research catalog

Purpose: learn which questions successful marketplaces ask per kind of thing, so the
Composer asks only relevant ones. Facts here describe public filters, labels and documented
schemas; nothing is copied (no code, copy or assets). Checked **2026-10-25**.

Provenance: **O** = observed directly on a public page in the browser without signing in;
**D** = read in official documentation; **I** = inference (marked, never presented as fact).
Access rules followed: public pages only, no login, no CAPTCHA or bot-protection bypass, a handful
of page loads in total. Where a site refused, it is recorded below instead of retried.

## Observed: Divar (Persian classifieds), public search filters (O)

Source pages: `divar.ir/s/tehran/{rent-apartment, buy-apartment, car, mobile-phones, jobs, services}`.
Filters are what the platform considers searchable; listing cards add the 1–2 attributes
shown inline.

| Category | Filter attributes (type) | Card shows | Notes |
|---|---|---|---|
| Residential rent | deposit range, monthly rent range (Toman), area m² range, rooms (choice 0,1,2,3,4,4+), building age range, floor range, parking / elevator / storage / balcony / renovated (yes toggles), total floors, units per floor, orientation, cooling, heating, floor material, bathroom type, hot-water supplier, advertiser type | title, rent, neighbourhood | Rent and deposit are two separate ranges; amenities are toggles, long-tail attributes are text with example hints |
| Residential sale | (page filters not captured; cards show area, price, neighbourhood) | area, price | **I**: same property attributes minus rent/deposit |
| Cars | price range, brand and trim (picker), model year range, mileage range (km), colour, domestic/foreign, chassis state, body state, engine state, fuel, gearbox, third-party insurance months, instalment possible, show swaps | mileage, price | Condition is split into three independent state pickers |
| Mobile phones | advertiser type, brand and model (picker), price range, internal storage, condition, brand authenticity, SIM count, RAM, colour, instalment possible, show swaps | condition (new / like new / used / needs repair), price | Condition is one short list with four values |
| Jobs | sub-category (admin, IT, sales, driver, teaching, ...), cooperation type, payment method, with insurance (toggle), remote possible (toggle), required experience range, no experience needed (toggle), military-service status | salary text, payment method | Salary is surfaced on the card as min/max/negotiable text |
| Services | sub-category only (catering, car, IT, legal, craft, beauty, cleaning, gardening, education, transport), has photo, has video, posted time | title, area | **Services have almost no structured filters**; the sub-category plus free text carries the listing |

Patterns: (1) sub-category sets the filter list; (2) numbers are ranges with a unit in the label;
(3) amenities are yes-toggles; (4) long-tail attributes are optional and use examples ("e.g. north");
(5) services stay simple.

## Observed: Torob (price comparison) (O)

Source: `torob.com/browse/99/` (laptops). Top-level toggles: new / used, in-person pickup, in-stock only.
Listing titles carry the comparable specification as chips: RAM, storage (SSD), processor,
screen size, graphics. Used items are tagged "used". **I**: comparison needs a small, standardized
spec per product family, expressed with one unit each.

## Observed: Achareh (home services) (O)

Source: `achareh.co` home page. Service tree: cleaning, building, repairing household items,
moving and freight, car, health and beauty, organisations, other. Each service is a named job
(for example washing-machine installation and repair, tile work). Messaging stresses "see the price
before ordering" and a three-step flow (find service, order, receive at your place).
The order form is behind sign-in and was **not inspected**. **I**: the service itself is the
category; the form needs place, time and a description rather than a spec sheet.

## Documented (D)

- **schema.org Accommodation:** accommodationCategory, floorSize, numberOfRooms, numberOfBedrooms,
  numberOfBathroomsTotal, floorLevel, yearBuilt, petsAllowed, leaseLength, occupancy, amenityFeature.
- **schema.org Vehicle:** bodyType, fuelType, vehicleTransmission, mileageFromOdometer, modelDate /
  productionDate, numberOfDoors, seatingCapacity, vehicleInteriorColor, numberOfPreviousOwners,
  knownVehicleDamages, meetsEmissionStandard, vehicleIdentificationNumber.
- **schema.org JobPosting:** title, employmentType (text; examples full-time, part-time, contract,
  temporary, internship), baseSalary + salaryCurrency, jobLocation, jobLocationType (telecommute),
  experienceRequirements, educationRequirements, skills, workHours, jobBenefits, validThrough,
  totalJobOpenings, jobStartDate.
- **schema.org Course / CourseInstance:** name, provider, inLanguage / availableLanguage,
  coursePrerequisites, timeRequired, courseMode (online, full-time), startDate, endDate, location.
- **eBay item specifics (Taxonomy API):** each category defines aspects with a required flag,
  a mode (free text or fixed list), single or multiple values, and a data type; buyers filter on them.
  (Exact enum names were not retrievable from the fetched page; the API spec was not read.)
- **Airbnb host help (index only):** amenities (guests filter on them), sleeping arrangements, number
  of bedrooms and bathrooms, check-in details, house rules, location precision. Flow details beyond
  the help index come from third-party guides and are **not** relied on.

## Not obtained

- eBay category pages returned an error page; Airbnb and Snapp listing flows are behind sign-in or an app;
  Divar and Torob posting forms require sign-in. None of those were bypassed.
- Employment, education, transport and activity coverage is therefore from Divar filters (jobs,
  services) and schema.org, not from observed posting forms.

## What the evidence says to build

1. A **small profile per kind of thing**, chosen by one tap, not one global field list.
2. **Essentials first (2–4 fields), the rest suggested.** Divar lists ~20 property filters but a
   seller should not see all of them up front.
3. **Selections beat typing** for brand-independent attributes (condition, fuel, gearbox, employment
   type, delivery mode); numbers carry their unit in the label.
4. **Amenities are yes-toggles**, shown only for property.
5. **Services stay light:** place, time, description, and at most a few facts such as experience and
   whether materials are included.
6. **Condition is one short list** (new, like new, used, needs repair), not three pickers.
7. **Conditional fields:** mileage and body state only when used; pay amount only when not "agreed".
8. The same key means the same thing everywhere (brand, condition, experience years) so filtering and
   matching work across profiles.
