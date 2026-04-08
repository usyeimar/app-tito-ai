from fastapi import FastAPI, Request, status
from fastapi.responses import JSONResponse
from fastapi.exceptions import RequestValidationError
from app.schemas.errors import APIErrorResponse, ErrorObject, ErrorDetail, ErrorSource


def setup_exception_handlers(app: FastAPI):
    """
    Setup global exception handlers for the application.
    """

    @app.exception_handler(RequestValidationError)
    async def validation_exception_handler(request: Request, exc: RequestValidationError):
        """
        Custom handler for validation errors (422).
        """
        details = []
        for error in exc.errors():
            # Error source (pointer to the field)
            loc = "/".join(str(v) for v in error.get("loc", []))
            
            details.append(
                ErrorDetail(
                    code="INVALID_ATTRIBUTE",
                    title=error.get("msg", "Validation error"),
                    source=ErrorSource(pointer=f"/{loc}")
                )
            )

        error_response = APIErrorResponse(
            error=ErrorObject(
                status=status.HTTP_422_UNPROCESSABLE_ENTITY,
                code="VALIDATION_ERROR",
                title="Validation failed for the request payload.",
                details=details
            ),
            _links={
                "self": {"href": str(request.url), "method": request.method},
                "docs": {"href": "/docs", "method": "GET"}
            }
        )
        
        return JSONResponse(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            content=error_response.model_dump(by_alias=True, exclude_none=True)
        )

    @app.exception_handler(Exception)
    async def global_exception_handler(request: Request, exc: Exception):
        """
        Global handler for unhandled exceptions (500).
        """
        error_response = APIErrorResponse(
            error=ErrorObject(
                status=status.HTTP_500_INTERNAL_SERVER_ERROR,
                code="INTERNAL_SERVER_ERROR",
                title="An unexpected error occurred.",
                details=[
                    ErrorDetail(
                        code="SERVER_ERROR",
                        title=str(exc)
                    )
                ]
            ),
            _links={
                "self": {"href": str(request.url), "method": request.method},
                "home": {"href": "/api/v1", "method": "GET"}
            }
        )

        return JSONResponse(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            content=error_response.model_dump(by_alias=True, exclude_none=True)
        )
